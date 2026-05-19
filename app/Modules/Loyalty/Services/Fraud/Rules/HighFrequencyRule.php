<?php

namespace App\Modules\Loyalty\Services\Fraud\Rules;

use App\Modules\Loyalty\Contracts\FraudRuleInterface;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudSignal;

class HighFrequencyRule implements FraudRuleInterface
{
    public function apply(array $data, Bill $bill): ?FraudSignal
    {
        $count = Bill::where('user_id', $bill->user_id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        return $count > 5
            ? new FraudSignal(50, 'Too many uploads in short time')
            : null;
    }
}