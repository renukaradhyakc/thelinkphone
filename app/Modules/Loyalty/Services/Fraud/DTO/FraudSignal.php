<?php

namespace App\Modules\Loyalty\Services\Fraud\DTO;

class FraudSignal
{
    public function __construct(
        public int $score,
        public string $reason
    ) {}
}