<?php

namespace App\Modules\Loyalty\Services\Fraud\Rules;

use App\Modules\Loyalty\Contracts\FraudRuleInterface;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Services\Fraud\DTO\FraudSignal;

class OCRDuplicateSignalRule implements FraudRuleInterface
{
    public function apply(array $data, Bill $bill): ?FraudSignal
    {
        if ($data['is_duplicate'] ?? false) {
            return new FraudSignal(20, 'OCR flagged duplicate');
        }

        return null;
    }
}