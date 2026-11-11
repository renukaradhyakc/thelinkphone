<?php

namespace App\Modules\Notifications\Services;

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Live location sharing -> the bookers who can see it. Never throws.
 */
class LocationNotifier
{
    public function __construct(
        private NotificationService $notifications,
        private RecipientResolver $recipients,
        private BookingNotifier $bookings
    ) {
    }

    public function started(Event $event, $startedAt): void
    {
        $this->safely($event, function () use ($event, $startedAt) {
            $session = $this->session($startedAt);
            $name = $this->ownerName($event);

            foreach ($this->viewers($event) as [$user, $booking]) {
                $this->notifications->notify(
                    $user->id,
                    'location.started',
                    'default',
                    "location.started:{$event->id}:{$session}:{$user->id}",
                    ['name' => $name],
                    ['event_schedule_id' => $booking->id, 'audience' => 'booker']
                );
            }
        });
    }

    public function stopped(Event $event, $startedAt): void
    {
        $this->safely($event, function () use ($event, $startedAt) {
            $session = $this->session($startedAt);

            if (! $this->claimEnd($event, $session)) {
                return;
            }

            $name = $this->ownerName($event);

            foreach ($this->viewers($event) as [$user, $booking]) {
                $this->notifications->notify(
                    $user->id,
                    'location.stopped',
                    'default',
                    "location.stopped:{$event->id}:{$session}:{$user->id}",
                    ['name' => $name],
                    ['event_schedule_id' => $booking->id, 'audience' => 'booker']
                );
            }
        });
    }

    /** Sharing ended by itself: after the 24 hour cap, or because the schedule finished. */
    public function autoStopped(Event $event, $startedAt, bool $capReached): void
    {
        $this->safely($event, function () use ($event, $startedAt, $capReached) {
            $session = $this->session($startedAt);
            $name = $this->ownerName($event);

            if ($this->claimEnd($event, $session)) {
                foreach ($this->viewers($event) as [$user, $booking]) {
                    $this->notifications->notify(
                        $user->id,
                        'location.auto_stopped',
                        $capReached ? 'cap_24h_other' : 'schedule_ended',
                        "location.auto_stopped:{$event->id}:{$session}:{$user->id}",
                        ['name' => $name],
                        ['event_schedule_id' => $booking->id, 'audience' => 'booker']
                    );
                }
            }

            $this->notifications->notify(
                $event->user_id,
                'location.auto_stopped',
                $capReached ? 'cap_24h_sharer' : 'schedule_ended',
                "location.auto_stopped:{$event->id}:{$session}:{$event->user_id}",
                ['name' => $name],
                ['audience' => 'owner']
            );
        });
    }

    /** @return array<int, array{0: User, 1: EventSchedule}> one entry per person, via their earliest booking that has not ended */
    private function viewers(Event $event): array
    {
        $bookings = EventSchedule::where('event_id', $event->id)
            ->where('status', EventSchedule::BOOKED)
            ->whereDate('schedule_date', '>=', now()->subDay()->toDateString())
            ->get()
            ->sortBy(fn ($b) => $this->bookings->startUtc($b)?->timestamp ?? PHP_INT_MAX);

        $viewers = [];

        foreach ($bookings as $booking) {
            $start = $this->bookings->startUtc($booking);

            if ($start === null) {
                continue;
            }

            $end = $this->bookings->endUtc($booking) ?? $start->copy()->addMinutes(30);

            if ($end->lte(now())) {
                continue;
            }

            $phone = trim((string) $booking->phone_call);
            $user = $phone === '' ? null : $this->recipients->uniqueUserByPhone($phone);

            if ($user === null || $user->id === $event->user_id || isset($viewers[$user->id])) {
                continue;
            }

            $viewers[$user->id] = [$user, $booking];
        }

        return $viewers;
    }

    private function claimEnd(Event $event, int $session): bool
    {
        return Cache::add("location.end:{$event->id}:{$session}", 1, now()->addDays(2));
    }

    private function session($startedAt): int
    {
        return $startedAt instanceof \DateTimeInterface ? $startedAt->getTimestamp() : (int) $startedAt;
    }

    private function ownerName(Event $event): string
    {
        return (string) (User::find($event->user_id)->full_name ?? '');
    }

    private function safely(Event $event, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::error('notifications.location_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }
    }
}