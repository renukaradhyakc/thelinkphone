<?php

namespace App\Modules\Loyalty\Contracts;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudSignal;

interface FraudRuleInterface
{
    public function apply(array $data, Bill $bill): ?FraudSignal;
}