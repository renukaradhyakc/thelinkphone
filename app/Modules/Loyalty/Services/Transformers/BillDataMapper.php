<?php

namespace App\Modules\Loyalty\Services\Transformers;


use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BillDataMapper
{
    public function map(array $data): array
    {
        // Detect provider (you will expand this later)
        if ($this->isTabScanner($data)) {
            return $this->mapTabScanner($data);
        }

        if ($this->isVeryfi($data)) {
            return $this->mapVeryfi($data);
        }

        // fallback (unknown provider)
        return $this->mapUnknown($data);
    }

    public function extractLineItems(array $raw, string $provider): array
    {
        $items = match ($provider) {
            'tabscanner' => $this->fromTabScanner($raw),
            'veryfi'     => $this->fromVeryfi($raw),
            default      => [],
        };

        return $this->normalizeItems($items);
    }

    private function isTabScanner(array $data): bool
    {
        return isset($data['status']) && isset($data['result']);
    }

    private function isVeryfi(array $data): bool
    {
        return isset($data['id']) || isset($data['meta']);
    }

    private function mapTabScanner(array $data): array
    {
        $result = $data['result'] ?? [];

        $normalized = [
            'amount' => $this->sanitizeAmount($result['total'] ?? null),

            'date' => $this->sanitizeDate($result['date'] ?? null),

            'vendor' => $this->sanitizeVendor($result['address'] ?? null),

            'invoice' => $result['invoiceNumber'] ?? null,

            'is_duplicate' => (bool) ($data['duplicate'] ?? false),

            'confidence' => $result['totalConfidence'] ?? null,
        ];

        return [
            // ✅ KEEP ORIGINAL STRUCTURE (IMPORTANT for your current job)
            'result' => [
                'total' => $normalized['amount'],
                'date' => $normalized['date'],
                'address' => $normalized['vendor'],
                'invoiceNumber' => $normalized['invoice'],
            ],

            'provider' => 'tabscanner',

            // ✅ ADD CLEAN STRUCTURE (for future)
            'normalized' => $normalized,

            // ✅ Pass through flags
            'duplicate' => $normalized['is_duplicate'],

            // ✅ Always keep raw
            'raw' => $data
        ];
    }

    private function mapVeryfi(array $data): array
    {
        $isDocument = $data['is_document'] ?? true;

        if (!$isDocument) {
            Log::warning('Veryfi: Submitted file is not a document', [
                'veryfi_id' => $data['id'] ?? null,
            ]);
        }
        $normalized = [
            'amount' => $this->sanitizeAmount($this->getValue($data['total'] ?? null)),
            'date' => $this->sanitizeDate($this->getValue($data['date'] ?? null)),
            'vendor' => $this->sanitizeVendor($this->getValue($data['vendor']['name'] ?? null)),
            'invoice' => $this->getValue($data['invoice_number'] ?? null),
            'is_duplicate' => (bool) ($data['is_duplicate'] ?? false),
            'duplicate_of' => $data['duplicate_of'] ?? null,
            'is_document' => $isDocument,
            'confidence' => $this->extractVeryfiConfidence($data),
        ];

        return [
            'provider' => 'veryfi',
            'normalized' => $normalized,
            'duplicate' => $normalized['is_duplicate'],
            'raw' => $data
        ];
    }

    private function mapUnknown(array $data): array
    {
        return [
            'provider' => 'unknown',
            'result' => [
                'total' => null,
                'date' => null,
                'address' => null,
                'invoiceNumber' => null,
            ],
            'normalized' => [
                'amount' => null,
                'date' => null,
                'vendor' => null,
                'invoice' => null,
                'is_duplicate' => false,
                'confidence' => null,
            ],
            'duplicate' => false,
            'raw' => $data
        ];
    }

    private function fromTabScanner(array $raw): array
    {
        $items = $raw['result']['lineItems'] ?? [];
        $out = [];

        foreach ($items as $item) {
            $out[] = [
                'description'     => $this->cleanDesc($item['descClean'] ?? $item['desc'] ?? ''),
                'raw_description' => $item['desc'] ?? null,
                'brand'           => null,
                'category'        => null,
                'categories'      => null,
                'item_type'       => 'product',
                'line_type'       => $item['lineType'] ?? null,
                'hsn_code'        => $this->extractHSN($item['desc'] ?? ''),
                'sku'             => $item['productCode'] ?? null,
                'upc'             => null,
                'unit'            => $item['unit'] ?: null,

                'quantity' => isset($item['qty']) ? (float) $item['qty'] : 1,
                'unit_price'      => (float) ($item['price'] ?? 0) ?: null,
                'line_total'      => (float) ($item['lineTotal'] ?? 0),
                'computed_total'  => null,

                'discount'        => (float) ($item['discount'] ?? 0),
                'tax_rate'        => null,
                'tax_amount'      => null,

                'confidence'      => null,
                'raw_meta'        => $item,
            ];
        }

        return $out;
    }

    private function fromVeryfi(array $raw): array
    {
        $items = $raw['line_items'] ?? [];
        $out = [];

        foreach ($items as $item) {
            $out[] = [
                'description'     => $item['description'] ?? '',
                'raw_description' => $item['full_description'] ?? null,
                'brand'           => $item['product_info']['brand'] ?? null,
                'category'        => $item['product_info']['category'][0] ?? null,
                'categories'      => $item['product_info']['category'] ?? null,
                'item_type'       => $item['type'] ?? 'product',
                'line_type'       => null,
                'hsn_code'        => $item['hsn'] ?? null,
                'sku'             => $item['sku'] ?? null,
                'upc'             => $item['upc'] ?? null,
                'unit'            => $item['unit_of_measure'] ?? null,

                'quantity'        => (float) ($item['quantity'] ?? 1),
                'unit_price'      => (float) ($item['price'] ?? 0) ?: null,
                'line_total'      => (float) ($item['total'] ?? 0),
                'computed_total'  => null,

                'discount'        => (float) ($item['discount'] ?? 0),
                'tax_rate'        => isset($item['tax_rate']) ? (float)$item['tax_rate'] : null,
                'tax_amount'      => isset($item['tax']) ? (float)$item['tax'] : null,

                'confidence'      => null,
                'raw_meta'        => $item,
            ];
        }

        return $out;
    }

    private function normalizeItems(array $items): array
    {
        $out = [];

        foreach ($items as $item) {

            if (($item['line_total'] ?? 0) <= 0) continue;
            if (($item['quantity'] ?? 0) <= 0) continue;

            $desc = strtolower($item['description']);

            if (str_contains($desc, 'cgst') || str_contains($desc, 'sgst')) continue;
            if (str_contains($desc, 'tax')) continue;

            if (!$item['computed_total'] && $item['unit_price']) {
                $item['computed_total'] = $item['quantity'] * $item['unit_price'];
            }

            $item['description'] = trim($item['description']);
            $item['position'] = count($out);

            $out[] = $item;
        }

        return $out;
    }

    // -------------------------
    // 🔧 Sanitizers (VERY IMPORTANT)
    // -------------------------

    private function extractHSN(?string $desc): ?string
    {
        if (!$desc) return null;

        if (preg_match('/^(\d{4,8})\b/', $desc, $m)) {
            return $m[1];
        }

        return null;
    }

    private function cleanDesc(?string $desc): ?string
    {
        if (!$desc) return null;

        // Remove HSN at start
        $desc = preg_replace('/^\d{4,8}\s+/', '', $desc);

        // Remove GST noise like "CGST @ 2.5%"
        $desc = preg_replace('/\(?\d*\)?\s*CGST.*$/i', '', $desc);
        $desc = preg_replace('/\(?\d*\)?\s*SGST.*$/i', '', $desc);

        return trim($desc) ?: null;
    }

    private function sanitizeAmount($amount): ?float
    {
        return $amount ? (float) $amount : null;
    }

    private function sanitizeDate($date): ?string
    {
        try {
            return $date ? Carbon::parse($date)->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function sanitizeVendor($vendor): ?string
    {
        if (!$vendor) return null;

        $vendor = trim($vendor);
        $vendor = preg_replace('/[^a-zA-Z0-9\s]/', '', $vendor);

        return $vendor ?: null;
    }

    private function extractVeryfiConfidence(array $data): ?float
    {
        $warnings = $data['warnings'] ?? [];
        if (count($warnings) === 0) return 0.95;
        if (count($warnings) <= 2) return 0.75;
        return 0.5;
    }

    private function getValue($field)
    {
        if (is_array($field)) {
            return $field['value'] ?? null;
        }
        return $field;
    }
}