<?php

namespace App\Modules\Loyalty\Services\OCR;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Loyalty\Contracts\OCRServiceInterface;

class TabScannerOCRService implements OCRServiceInterface
{
    public function extractText(string $filePath): array
    {

        if (!Storage::disk('private')->exists($filePath)) {
            throw new \Exception("File missing: " . $filePath);
        }

        $fileContent = Storage::disk('private')->path($filePath);

        Log::info('TabScanner API KEY', [
            'key' => config('services.tabscanner.key')
        ]);
        Log::info('Sending file to OCR', ['path' => $filePath]);

        $response = Http::withoutVerifying()
        ->withHeaders([
            'apikey' => config('services.tabscanner.key') // ✅ correct place
        ])
        ->attach(
            'receiptImage',
            fopen($fileContent, 'r'),
            basename($filePath)
        )->post('https://api.tabscanner.com/api/2/process');

        Log::info('OCR response', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        if (!$response->successful()) {
            Log::error('TabScanner failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('TabScanner API failed');
        }

        $upload = $response->json();

        if (!isset($upload['token'])) {
            throw new \Exception('OCR upload failed');
        }

        $token = $upload['token'];

        sleep(3);

        $result = Http::withoutVerifying()
        ->withHeaders([
            'apikey' => config('services.tabscanner.key')
        ])
        ->get('https://api.tabscanner.com/api/result/' .$token);

        if (!$result->successful()) {
            throw new \Exception('Failed to fetch OCR result');
        }

        $data = $result->json();

        Log::info('OCR FINAL RESULT', $data);
        
        return $data;
    }
}