<?php

namespace App\Modules\Notifications\Services;

use App\Models\PhoneSchedule;
use App\Models\User;
use App\Models\UserSchedule;
use App\Modules\Notifications\Models\NotificationOutbox;
use App\Modules\Notifications\Repositories\OutboxRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phone schedule assigned / modified / revoked -> the contact whose number it is. Never throws.
 */
class PhoneScheduleNotifier
{
    public function __construct(
        private NotificationService $notifications,
        private RecipientResolver $recipients,
        private NotificationRegistry $registry,
        private OutboxRepository $outbox
    ) {
    }

    /** A number was assigned a schedule, switched to another, or had it removed. Sent immediately. */
    public function changed(int $ownerId, string $normalizedPhone, ?PhoneSchedule $before, ?PhoneSchedule $after): void
    {
        try {
            $variant = match (true) {
                $before === null && $after !== null => 'assigned',
                $before !== null && $after === null => 'revoked',
                $before !== null && $after !== null && $this->signature($before) !== $this->signature($after) => 'modified',
                default => null,
            };

            if ($variant === null) {
                return;
            }

            $contact = $this->recipients->uniqueUserByPhone($normalizedPhone);
            $owner = User::find($ownerId);

            if ($contact === null || $owner === null || $contact->id === $owner->id) {
                return;
            }

            $this->notifications->notify(
                $contact->id,
                'phone_schedule.changed',
                $variant,
                "phone_schedule.changed:{$ownerId}:{$contact->id}:{$variant}:".substr(md5($this->signature($after)), 0, 8).':'.intdiv(time(), 60),
                ['name' => $owner->full_name, 'hours' => $this->hours($after)],
                ['audience' => 'contact', 'owner_id' => $ownerId]
            );
        } catch (Throwable $e) {
            Log::error('notifications.phone_schedule.changed_failed', ['owner_id' => $ownerId, 'error' => $e->getMessage()]);
        }
    }

    /** The hours of a reusable schedule were saved: queue a notice for each assigned contact. */
    public function scheduleHoursSaved(int $ownerId, int $scheduleId, string $before): void
    {
        try {
            if ($this->hoursSignature($scheduleId) === $before) {
                return;
            }

            $quiet = max(1, (int) $this->registry->rule('event_change_quiet_minutes', 3));

            foreach (PhoneSchedule::where('user_id', $ownerId)->where('schedule_id', $scheduleId)->get() as $assignment) {
                $contact = $this->recipients->uniqueUserByPhone($assignment->phone_number_normalized);

                if ($contact === null || $contact->id === $ownerId) {
                    continue;
                }

                $key = "phone_schedule.hours:{$assignment->id}:{$contact->id}";

                $pending = NotificationOutbox::where('dedupe_key', $key)
                    ->where('status', NotificationOutbox::PENDING)
                    ->first();

                $this->outbox->schedule(
                    $contact->id,
                    null,
                    'phone_schedule.changed',
                    'modified',
                    now()->addMinutes($quiet),
                    $key,
                    [
                        'audience' => 'contact',
                        'owner_id' => $ownerId,
                        'phone_schedule_id' => $assignment->id,
                        'schedule_id' => $scheduleId,
                        'before' => $pending ? ($pending->context['before'] ?? $before) : $before,
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::error('notifications.phone_schedule.hours_failed', ['schedule_id' => $scheduleId, 'error' => $e->getMessage()]);
        }
    }

    /** Called by notifications:dispatch-due. Returns 'sent' or 'cancelled'. */
    public function sendHoursChange(NotificationOutbox $row): string
    {
        $context = $row->context ?? [];
        $assignment = PhoneSchedule::with('schedule')->find($context['phone_schedule_id'] ?? 0);
        $contact = User::find($row->user_id);
        $owner = User::find($context['owner_id'] ?? 0);
        $scheduleId = (int) ($context['schedule_id'] ?? 0);

        $stillAssigned = $assignment && (int) $assignment->schedule_id === $scheduleId;

        if (! $stillAssigned || $contact === null || $owner === null
            || $this->hoursSignature($scheduleId) === ($context['before'] ?? null)) {
            Log::info('notifications.phone_hours_skipped', ['outbox_id' => $row->id]);

            return 'cancelled';
        }

        $this->notifications->notify(
            $contact->id,
            'phone_schedule.changed',
            'modified',
            "phone_schedule.hours:{$assignment->id}:{$contact->id}:".$row->notify_at->timestamp,
            ['name' => $owner->full_name, 'hours' => $assignment->schedule->schedule_name ?? 'a schedule'],
            ['audience' => 'contact', 'owner_id' => $owner->id]
        );

        return 'sent';
    }

    /** A fingerprint of a reusable schedule's weekly hours. */
    public function hoursSignature(int $scheduleId): string
    {
        return 'h'.UserSchedule::where('schedule_id', $scheduleId)
            ->whereNull('event_id')
            ->whereNull('phone_schedule_id')
            ->get(['day_of_week', 'from_time', 'to_time'])
            ->map(fn ($slot) => "{$slot->day_of_week}|{$slot->from_time}|{$slot->to_time}")
            ->sort()
            ->implode(',');
    }

    private function signature(?PhoneSchedule $schedule): string
    {
        if ($schedule === null) {
            return '';
        }

        if ($schedule->schedule_id) {
            return 's'.$schedule->schedule_id;
        }

        return 'c'.$schedule->userSchedules
            ->map(fn ($slot) => "{$slot->day_of_week}|{$slot->from_time}|{$slot->to_time}")
            ->sort()
            ->implode(',');
    }

    private function hours(?PhoneSchedule $schedule): string
    {
        if ($schedule === null || ! $schedule->schedule_id) {
            return 'custom hours';
        }

        return $schedule->schedule->schedule_name ?? 'a schedule';
    }
}