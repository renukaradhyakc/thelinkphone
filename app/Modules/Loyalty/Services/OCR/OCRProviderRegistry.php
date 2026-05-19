<?php

namespace App\Modules\Loyalty\Services\OCR;

use Illuminate\Support\Facades\Http;
use App\Modules\Loyalty\Contracts\OCRServiceInterface;

class OCRProviderRegistry {
    private array $providers = [];

    public function register(string $name, OCRServiceInterface $provider): void {
        $this->providers[$name] = $provider;
    }

    public function getChain(): array {
        $order = config('loyalty.ocr_providers', ['tabscanner', 'veryfi']);
        return array_filter(array_map(
            fn($name) => $this->providers[$name] ?? null, $order
        ));
    }
}