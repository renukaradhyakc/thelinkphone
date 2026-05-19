<?php

namespace App\Modules\Loyalty\Services\Fraud\Rules;

use App\Modules\Loyalty\Contracts\FraudRuleInterface;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudSignal;

class SameAmountSameDateRule implements FraudRuleInterface
{
    public function apply(array $data, Bill $bill): ?FraudSignal
    {
        if (!$data['amount'] || !$data['date']) {
            return null;
        }

        $exists = Bill::where('user_id', $bill->user_id)
            ->where('amount', $data['amount'])
            ->whereDate('bill_date', $data['date'])
            ->where('id', '!=', $bill->id)
            ->exists();

        return $exists
            ? new FraudSignal(40, 'Same amount + date for same user')
            : null;
    }
}