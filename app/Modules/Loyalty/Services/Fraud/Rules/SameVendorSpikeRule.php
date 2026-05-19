<?php

namespace App\Modules\Loyalty\Services\Fraud\Rules;

use App\Modules\Loyalty\Contracts\FraudRuleInterface;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudSignal;

class SameVendorSpikeRule implements FraudRuleInterface
{
    public function apply(array $data, Bill $bill): ?FraudSignal
    {
        if (!$data['vendor']) return null;

        $count = Bill::where('user_id', $bill->user_id)
            ->where('vendor_name', $data['vendor'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count > 3
            ? new FraudSignal(30, 'Same vendor repeated too often')
            : null;
    }
}