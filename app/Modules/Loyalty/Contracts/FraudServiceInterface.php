<?php

namespace App\Modules\Loyalty\Contracts;

use App\Modules\Loyalty\Models\Bill;

interface FraudServiceInterface {
    public function isDuplicate(array $data, Bill $bill): bool;
}