<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Repositories\OutboxRepository;
use App\Modules\Notifications\Services\BookingNotifier;
use App\Modules\Notifications\Services\PhoneScheduleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchDueCommand extends Command
{
    protected $signature = 'notifications:dispatch-due {--limit=50}';

    protected $description = 'Send scheduled reminders that are due';

    public function handle(OutboxRepository $outbox, BookingNotifier $bookings, PhoneScheduleNotifier $phoneSchedules): int
    {
        $released = $outbox->releaseStaleClaims();
        $sent = $cancelled = $failed = 0;

        foreach ($outbox->claimDue((int) $this->option('limit')) as $row) {
            try {
                $result = match ($row->type) {
                    'booking.reminder' => $bookings->sendReminder($row),
                    'booking.changed' => $bookings->sendChange($row),
                    'phone_schedule.changed' => $phoneSchedules->sendHoursChange($row),
                    default => null,
                };

                if ($result === null) {
                    $outbox->markFailed($row);
                    $failed++;
                    continue;
                }

                if ($result === 'sent') {
                    $outbox->markSent($row);
                    $sent++;
                } else {
                    $outbox->markCancelled($row);
                    $cancelled++;
                }
            } catch (Throwable $e) {
                // stays claimed: releaseStaleClaims() retries it later and fails it after 3 attempts
                Log::error('notifications.dispatch_due_failed', ['outbox_id' => $row->id, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        $this->info("sent {$sent}, cancelled {$cancelled}, failed {$failed}, stale released {$released}");

        return self::SUCCESS;
    }
}