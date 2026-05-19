<?php

namespace App\Modules\Loyalty\Contracts;

interface PointsServiceInterface {
    public function calculate(float $amount): int;
}