<?php

namespace App\Modules\Notifications\Observers;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\FraudLog;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Sends the loyalty.result notification whenever a bill's status changes.
 *
 * - Runs after the database transaction commits, so the points row already
 *   exists and a rolled-back job sends nothing.
 * - Catches every path: the queue job, admin approve and admin reject.
 * - Never throws. A notification problem must not turn a finished bill into a failed one.
 */
class BillStatusObserver
{
    public bool $afterCommit = true;

    public function updated(Bill $bill): void
    {
        if (! $bill->wasChanged('status')) {
            return;
        }

        try {
            $reason = $this->reasonFor($bill);

            if ($reason === null) {
                return;
            }

            $params = [];

            if ($reason === 'success') {
                $params = [
                    'count' => (int) LoyaltyPoint::where('bill_id', $bill->id)->value('points'),
                    'total' => (int) LoyaltyPoint::where('user_id', $bill->user_id)->sum('points'),
                ];
            }

            app(NotificationService::class)->notify(
                (int) $bill->user_id,
                'loyalty.result',
                $reason,
                "loyalty.result:{$bill->id}:{$reason}",
                $params,
                ['bill_id' => $bill->id]
            );
        } catch (\Throwable $exception) {
            Log::warning('Loyalty notification failed', [
                'bill_id' => $bill->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function reasonFor(Bill $bill): ?string
    {
        return match ($bill->status) {
            Bill::STATUS_DONE => 'success',
            Bill::STATUS_FAILED => 'failed',
            Bill::STATUS_INVALID => 'invalid',
            Bill::STATUS_REVIEW => 'review',
            Bill::STATUS_DUPLICATE => $this->wasRejected($bill) ? 'rejected' : 'duplicate',
            default => null,
        };
    }

    /**
     * Both a fraud rejection and an admin rejection set the status to "duplicate".
     * The fraud log tells them apart from a real duplicate.
     */
    private function wasRejected(Bill $bill): bool
    {
        return FraudLog::where('bill_id', $bill->id)->value('decision') === 'rejected';
    }
}