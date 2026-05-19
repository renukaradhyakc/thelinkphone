<?php

namespace App\Modules\Loyalty\Services\LineItems;

use App\Modules\Loyalty\Services\Points\ItemPointsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LineItemService
{
    protected ItemPointsService $pointsService;

    public function __construct(ItemPointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }


    public function prepare(array $items, int $billId, string $provider): array
    {
        return collect($items)
            ->map(function ($item, $index) use ($billId, $provider) {

                return [
                    'bill_id'        => $billId,
                    'provider'       => $provider,

                    'description'    => $item['description'] ?? null,
                    'raw_description'=> $item['raw_description'] ?? null,

                    'brand'          => $item['brand'] ?? null,
                    'category'       => $item['category'] ?? null,
                    'categories'     => !empty($item['categories']) ? json_encode($item['categories']) : null,

                    'item_type'      => $item['item_type'] ?? 'product',
                    'line_type'      => $item['line_type'] ?? null,

                    'hsn_code'       => $item['hsn_code'] ?? null,
                    'sku'            => $item['sku'] ?? null,
                    'upc'            => $item['upc'] ?? null,
                    'unit'           => $item['unit'] ?? null,

                    'quantity'       => isset($item['quantity']) ? (float) $item['quantity'] : 1,
                    'unit_price'     => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                    'line_total'     => (float) $item['line_total'],
                    'computed_total' => $item['computed_total'] ?? null,

                    'discount'       => (float) ($item['discount'] ?? 0),
                    'tax_rate'       => isset($item['tax_rate']) ? (float) $item['tax_rate'] : null,
                    'tax_amount'     => isset($item['tax_amount']) ? (float) $item['tax_amount'] : null,

                    'is_eligible'    => $this->isEligible($item),
                    'points_earned'  => 0,

                    'confidence'     => $item['confidence'] ?? null,
                    'raw_meta'       => !empty($item['raw_meta']) ? json_encode($item['raw_meta']) : null,

                    'position'       => $index,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            })
            ->values()
            ->all();
    }

    private function isEligible(array $item): bool
    {
        if (($item['line_total'] ?? 0) <= 0) return false;

        $type = strtolower($item['item_type'] ?? '');

        if (in_array($type, ['tax', 'fee', 'discount', 'service'])) {
            return false;
        }

        $desc = strtolower($item['description'] ?? '');

        if (str_contains($desc, 'cgst') || str_contains($desc, 'sgst')) {
            return false;
        }

        return true;
    }
}