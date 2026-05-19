<?php

namespace App\Modules\Loyalty\Services\Fraud\DTO;

class FraudResult
{
    public function __construct(
        public int $score,
        public array $reasons
    ) {}

    public function isFraud(): bool
    {
        return $this->score >= 70;
    }
}