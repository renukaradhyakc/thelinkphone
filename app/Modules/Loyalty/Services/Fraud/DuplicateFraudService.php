<?php

namespace App\Modules\Loyalty\Services\Fraud;
use App\Modules\Loyalty\Contracts\FraudServiceInterface;
use App\Modules\Loyalty\Models\Bill;

class DuplicateFraudService implements FraudServiceInterface
{
    public function isDuplicate(array $data, Bill $bill): bool
    {
        return false;
    }
}