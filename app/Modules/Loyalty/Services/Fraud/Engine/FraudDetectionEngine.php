<?php

namespace App\Modules\Loyalty\Services\Fraud\Engine;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudResult;
use App\Modules\Loyalty\Contracts\FraudRuleInterface;

class FraudDetectionEngine
{
    protected array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function analyze(array $data, Bill $bill): FraudResult
    {
        $score = 0;
        $reasons = [];

        foreach ($this->rules as $rule) {
            $signal = $rule->apply($data, $bill);

            if ($signal) {
                $score += $signal->score;
                $reasons[] = $signal->reason;
            }
        }

        return new FraudResult($score, $reasons);
    }
}