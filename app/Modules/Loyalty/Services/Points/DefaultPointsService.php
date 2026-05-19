<?php

namespace App\Modules\Loyalty\Services\Points;
use App\Modules\Loyalty\Contracts\PointsServiceInterface;
use App\Modules\Loyalty\Models\Bill;

class DefaultPointsService implements PointsServiceInterface
{
    public function calculate(float $amount): int
    {
        return (int) ($amount / 10);
    }
}