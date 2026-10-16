<?php

namespace App\Modules\Notifications\Services;

use App\Models\EventSchedule;
use App\Models\User;
use App\Models\Event;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Str;

/**
 * Booking events -> notifications. Never throws: a notification problem must not break a booking.
 */
class BookingNotifier
{
    public function __construct(
        private NotificationService $notifications,
        private NotificationRegistry $registry,
        private RecipientResolver $recipients,
        private OutboxRepository $outbox
    ) {
    }

    /** A booking became confirmed (free, or paid and completed). */
    public function created(EventSchedule $booking): void
    {
        $this->safely('created', $booking, function () use ($booking) {
            $booking->loadMissing(['event', 'user']);

            $parties = [];
            if ($booking->user) {
                $parties['owner'] = $booking->user;
            }

            $booker = $this->booker($booking);
            if ($booker && $booker->id !== $booking->user_id) {
                $parties['booker'] = $booker;
            }

            foreach ($parties as $audience => $user) {
                $this->notifications->notify(
                    $user->id,
                    'booking.created',
                    $audience,
                    "booking.created:{$booking->id}:{$user->id}",
                    $this->params($booking, $user),
                    ['event_schedule_id' => $booking->id, 'audience' => $audience]
                );
            }

            $this->scheduleReminders($booking, $parties);
        });
    }

    /**
     * owner_cancelled / event_deleted -> the booker is told.
     * booker_cancelled -> the owner is told.
     */
    public function cancelled(EventSchedule $booking, string $variant = 'owner_cancelled'): void
    {
        $this->safely('cancelled', $booking, function () use ($booking, $variant) {
            $this->outbox->cancelPendingForBooking($booking->id);
            $booking->loadMissing(['event', 'user']);

            $audience = $variant === 'booker_cancelled' ? 'owner' : 'booker';
            $user = $audience === 'owner' ? $booking->user : $this->booker($booking);

            if ($user === null || $user->id === ($audience === 'booker' ? $booking->user_id : null)) {
                return;
            }

            $this->notifications->notify(
                $user->id,
                'booking.cancelled',
                $variant,
                "booking.cancelled:{$booking->id}:{$user->id}",
                $this->params($booking, $user),
                ['event_schedule_id' => $booking->id, 'audience' => $audience]
            );
        });
    }

    /** @param array $changes field => ['old' => ..., 'new' => ...] */
    public function eventChanged(Event $event, string $variant, array $changes = []): void
    {
        try {
            $quiet = max(1, (int) $this->registry->rule('event_change_quiet_minutes', 3));
            $sendNow = (int) $this->registry->rule('event_change_send_now_within_minutes', 60);
            $notifyAt = now()->addMinutes($quiet);

            $bookings = EventSchedule::with(['event', 'user'])
                ->where('event_id', $event->id)
                ->where('status', EventSchedule::BOOKED)
                ->whereDate('schedule_date', '>=', now()->subDay()->toDateString())
                ->get()
                ->sortBy(fn ($b) => $this->startUtc($b)?->timestamp ?? PHP_INT_MAX);

            $told = [];

            foreach ($bookings as $booking) {
                $start = $this->startUtc($booking);
                $booker = $this->booker($booking);

                if ($start === null || $start->lte(now()) || $booker === null || $booker->id === $booking->user_id) {
                    continue;
                }

                // tell each person once per edit, via their earliest booking
                if (isset($told[$booker->id])) {
                    continue;
                }
                $told[$booker->id] = true;

                if ($start->lte(now()->addMinutes($sendNow))) {
                    $this->notifications->notify(
                        $booker->id,
                        'booking.changed',
                        $variant,
                        "booking.changed:{$booking->id}:{$booker->id}:{$variant}:now".intdiv(time(), $quiet * 60).':'.substr(md5(json_encode($changes)), 0, 8),
                        $this->changeParams($booking, $booker, $changes),
                        ['event_schedule_id' => $booking->id, 'audience' => 'booker', 'changes' => $this->changeList($changes)]
                    );
                    continue;
                }

                $key = "booking.changed:{$booking->id}:{$variant}:{$booker->id}";

                $this->outbox->schedule(
                    $booker->id,
                    $booking->id,
                    'booking.changed',
                    $variant,
                    $notifyAt,
                    $key,
                    ['audience' => 'booker', 'changes' => $this->mergeChanges($key, $changes)]
                );
            }
        } catch (Throwable $e) {
            Log::error('notifications.booking.event_changed_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }
    }

    /** Called by notifications:dispatch-due for booking.changed rows. Returns 'sent' or 'cancelled'. */
    public function sendChange(NotificationOutbox $row): string
    {
        $booking = EventSchedule::with(['event', 'user'])->find($row->event_schedule_id);
        $user = User::find($row->user_id);
        $start = $booking ? $this->startUtc($booking) : null;

        $context = $row->context ?? [];
        $noNetChange = array_key_exists('changes', $context) && $context['changes'] === [];

        if ($booking === null || $booking->event === null || $user === null || $noNetChange
            || (int) $booking->status !== EventSchedule::BOOKED || $start === null || $start->lt(now())) {
            Log::info('notifications.change_skipped', ['outbox_id' => $row->id, 'no_net_change' => $noNetChange]);

            return 'cancelled';
        }

        $changes = $context['changes'] ?? [];

        $this->notifications->notify(
            $user->id,
            'booking.changed',
            $row->reason,
            "booking.changed:{$booking->id}:{$user->id}:".$row->notify_at->timestamp,
            $this->changeParams($booking, $user, $changes),
            ['event_schedule_id' => $booking->id, 'audience' => 'booker', 'changes' => $this->changeList($changes)]
        );

        return 'sent';
    }

    /** Called by notifications:dispatch-due. Returns 'sent' or 'cancelled'. */
    public function sendReminder(NotificationOutbox $row): string
    {
        $booking = EventSchedule::with(['event', 'user'])->find($row->event_schedule_id);

        if ($booking === null || $booking->event === null || (int) $booking->status !== EventSchedule::BOOKED) {
            return 'cancelled';
        }

        $variant = $row->reason;
        $minutes = (int) ($this->registry->rule('reminder_offsets_minutes', [])[$variant] ?? 0);
        $start = $this->startUtc($booking);

        if ($start === null) {
            return 'cancelled';
        }

        // the booking time was edited after this row was scheduled, or the slot already began
        $expected = $start->copy()->subMinutes($minutes);
        if (abs($expected->diffInMinutes($row->notify_at)) > 1 || $start->lt(now()->subMinutes(2))) {
            return 'cancelled';
        }

        $user = User::find($row->user_id);
        if ($user === null) {
            return 'cancelled';
        }

        $this->notifications->notify(
            $user->id,
            'booking.reminder',
            $variant,
            $row->dedupe_key,
            $this->params($booking, $user),
            ['event_schedule_id' => $booking->id, 'audience' => $row->context['audience'] ?? 'owner']
        );

        return 'sent';
    }

    private function scheduleReminders(EventSchedule $booking, array $parties): void
    {
        $start = $this->startUtc($booking);

        if ($start === null) {
            return;
        }

        foreach ($this->registry->rule('reminder_offsets_minutes', []) as $variant => $minutes) {
            $notifyAt = $start->copy()->subMinutes((int) $minutes);

            if ($notifyAt->lte(now())) {
                continue;
            }

            foreach ($parties as $audience => $user) {
                $this->outbox->schedule(
                    $user->id,
                    $booking->id,
                    'booking.reminder',
                    $variant,
                    $notifyAt,
                    "booking.reminder:{$booking->id}:{$variant}:{$user->id}",
                    ['audience' => $audience]
                );
            }
        }
    }

    public function startUtc(EventSchedule $booking): ?Carbon
    {
        try {
            $booking->loadMissing('user');

            $date = Carbon::parse($booking->schedule_date)->format('Y-m-d');
            $start = trim(preg_split('/\s*-\s*/', (string) $booking->slot_time)[0] ?? '');

            return Carbon::parse("$date $start", $this->timezone($booking->user))->utc();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function timezone(?User $user): string
    {
        $key = $user->timezone ?? null;

        return ($key !== null && isset(User::TIME_ZONE_ARRAY[$key]))
            ? User::TIME_ZONE_ARRAY[$key]
            : config('app.timezone');
    }

    private function booker(EventSchedule $booking): ?User
    {
        $phone = trim((string) $booking->phone_call);

        return $phone === '' ? null : $this->recipients->uniqueUserByPhone($phone);
    }

    private function params(EventSchedule $booking, User $recipient): array
    {
        $start = $this->startUtc($booking);

        return [
            'event' => $booking->event->name ?? '',
            'name' => $booking->name,
            'time' => $start
                ? $start->copy()->setTimezone($this->timezone($recipient))->format('d M, h:i A')
                : trim($booking->schedule_date.' '.$booking->slot_time),
        ];
    }

    /** Keeps the earliest old value and the latest new value per field; a field that ends where it started is dropped. */
    private function mergeChanges(string $dedupeKey, array $new): array
    {
        $pending = NotificationOutbox::where('dedupe_key', $dedupeKey)
            ->where('status', NotificationOutbox::PENDING)
            ->first();

        $merged = $pending ? ($pending->context['changes'] ?? []) : [];

        foreach ($new as $field => $change) {
            $old = array_key_exists($field, $merged) ? $merged[$field]['old'] : $change['old'];
            $merged[$field] = ['old' => $old, 'new' => $change['new']];

            if ((string) $old === (string) $change['new']) {
                unset($merged[$field]);
            }
        }

        return $merged;
    }

    private function changeParams(EventSchedule $booking, User $recipient, array $changes): array
    {
        return $this->params($booking, $recipient) + ['changes' => $this->summarize($changes)];
    }

    private function summarize(array $changes): string
    {
        $parts = [];

        foreach ($changes as $field => $change) {
            $old = $this->display($field, $change['old']);
            $new = $this->display($field, $change['new']);

            $parts[] = match ($field) {
                'name' => 'name changed to “'.Str::limit($new, 40).'”',
                'description' => 'description updated',
                'event_location' => 'meeting mode changed from '.($old ?: 'unset').' to '.($new ?: 'unset'),
                'location' => $new !== '' ? 'location changed to “'.Str::limit($new, 60).'”' : 'location updated',
                default => $field.' updated',
            };
        }

        return $parts ? Str::limit(implode('; ', $parts), 140) : 'check the details';
    }

    /** The structured version stored in the notification's data, for a before/after screen. */
    private function changeList(array $changes): array
    {
        $list = [];

        foreach ($changes as $field => $change) {
            $list[] = [
                'field' => $field,
                'old' => Str::limit($this->display($field, $change['old']), 80),
                'new' => Str::limit($this->display($field, $change['new']), 80),
            ];
        }

        return $list;
    }

    private function display(string $field, $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($field === 'event_location') {
            return (string) (Event::LOCATION_ARRAY[$value] ?? $value);
        }

        return (string) $value;
    }

    private function safely(string $action, EventSchedule $booking, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::error("notifications.booking.{$action}_failed", [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}