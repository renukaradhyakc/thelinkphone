<?php

namespace App\Modules\Loyalty\Services\Points;

class ItemPointsService
{
    public function calculate(array $item): int
    {
        if (!($item['is_eligible'] ?? true)) {
            return 0;
        }

        $amount = (float) ($item['line_total'] ?? 0);

        if ($amount <= 0) {
            return 0;
        }

        $multiplier = $this->getMultiplier($item);

        return (int) floor(($amount / 10) * $multiplier);
    }

    private function getMultiplier(array $item): float
    {
        $category = strtolower($item['category'] ?? '');
        $brand = strtolower($item['brand'] ?? '');

        // Category campaigns
        return match (true) {

            str_contains($category, 'electronics') => 5,

            str_contains($category, 'grocery') => 2,

            str_contains($category, 'fashion') => 3,

            str_contains($category, 'medicine') => 1,

            str_contains($category, 'alcohol') => 0,

            // Example brand boost
            str_contains($brand, 'apple') => 10,

            default => 1,
        };
    }
}