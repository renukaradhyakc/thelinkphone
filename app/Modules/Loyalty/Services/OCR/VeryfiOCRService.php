<?php

namespace App\Modules\Loyalty\Services\OCR;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Modules\Loyalty\Contracts\OCRServiceInterface;

class VeryfiOCRService implements OCRServiceInterface
{
    public function extractText(string $filePath): array
    {
        if (!Storage::disk('private')->exists($filePath)) {
            throw new \Exception("Veryfi: File not found on disk: {$filePath}");
        }

        $absolutePath = Storage::disk('private')->path($filePath);
        $fileContent  = file_get_contents($absolutePath);

        if ($fileContent === false) {
            throw new \Exception("Veryfi: Could not read file: {$filePath}");
        }

        $base64 = base64_encode($fileContent);
        $mimeType = mime_content_type($absolutePath);

        $fileData = "data:{$mimeType};base64,{$base64}";

        $headers = [
            'CLIENT-ID'     => config('services.veryfi.client_id'),
            'AUTHORIZATION' => 'apikey ' . config('services.veryfi.username') 
                               . ':' . config('services.veryfi.api_key'),
            'Accept'        => 'application/json',
        ];

        Log::info('Sending to Veryfi', [
            'file_path' => $filePath,
            'mime_type' => $mimeType,
        ]);

        $response = Http::withoutVerifying()
            ->withHeaders($headers)
            ->timeout(60)
            ->post('https://api.veryfi.com/api/v8/partner/documents', [
                'file_data'          => $fileData,
                'file_name'          => basename($filePath),
                'confidence_details' => true,   // get per-field confidence scores
                'boost_mode'         => false,  // needed for enrichments
                'auto_delete'        => false,  // keep in Veryfi for audit trail
            ]);

        Log::info('Veryfi response', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if ($response->status() === 429) {
            throw new \Exception('Veryfi rate limit hit (60 req/s)');
        }

        if (!$response->successful()) {
            throw new \Exception(
                'Veryfi API failed: ' . $response->status() . ' — ' . $response->body()
            );
        }

        $data = $response->json();

        // Validate we got a real document back
        if (empty($data['id'])) {
            throw new \Exception('Veryfi: Response missing document id — ' . json_encode($data));
        }

        return $data;
    }
}