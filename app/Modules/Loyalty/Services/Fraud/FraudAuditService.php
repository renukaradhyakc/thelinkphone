<?php

namespace App\Modules\Loyalty\Services\Fraud;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\FraudLog;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudResult;

class FraudAuditService
{
    private int $rejectThreshold;
    private int $reviewThreshold;

    public function __construct()
    {
        $this->rejectThreshold = config('loyalty.fraud.reject_threshold', 90);
        $this->reviewThreshold = config('loyalty.fraud.review_threshold', 60);
    }

    public function handle(Bill $bill, FraudResult $result): string
    {
        $decision = $this->decide($result);

        try{
            FraudLog::updateOrCreate(
                ['bill_id' => $bill->id],
                [
                    'user_id' => $bill->user_id,
                    'score' => $result->score,
                    'reasons' => $result->reasons, // cast as array in model
                    'decision' => $decision,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'fraud_logs_bill_id_unique')) {
                return $decision;
            }
            throw $e;
        }

        return $decision;
    }

    private function decide(FraudResult $result): string
    {
        if ($result->score >= $this->rejectThreshold) {
            return 'rejected';
        }

        if ($result->score >= $this->reviewThreshold) {
            return 'review';
        }

        return 'approved';
    }
}