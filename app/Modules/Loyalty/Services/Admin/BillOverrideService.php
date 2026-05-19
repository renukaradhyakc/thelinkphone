<?php

namespace App\Modules\Loyalty\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\FraudLog;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Contracts\PointsServiceInterface;

class BillOverrideService
{
    public function __construct(
        protected PointsServiceInterface $pointsService
    ) {}

    public function approve(Bill $bill, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($bill, $adminId, $note) {

            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            // Idempotency
            if ($bill->status === Bill::STATUS_DONE) {
                return;
            }

            // Update bill
            $bill->markApprovedByAdmin($adminId, $note);

            // Ensure points (idempotent)
            LoyaltyPoint::firstOrCreate(
                ['bill_id' => $bill->id],
                [
                    'user_id' => $bill->user_id,
                    'points' => $this->pointsService->calculate($bill->amount),
                ]
            );

            // Update fraud log
            $this->updateFraudLog($bill, $adminId, 'approved');
        });
    }

    public function reject(Bill $bill, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($bill, $adminId, $note) {

            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            // Idempotency
            if ($bill->status === Bill::STATUS_DUPLICATE) {
                return;
            }

            // Remove points safely
            LoyaltyPoint::where('bill_id', $bill->id)->delete();

            // Update bill
            $bill->markRejectedByAdmin($adminId, $note);

            // Update fraud log
            $this->updateFraudLog($bill, $adminId, 'rejected');
        });
    }

    private function updateFraudLog(Bill $bill, int $adminId, string $status): void
    {
        FraudLog::updateOrCreate(
            ['bill_id' => $bill->id],
            [
                'reviewed_by' => $adminId,
                'override_status' => $status,
                'decision' => $status,
            ]
        );
    }
}