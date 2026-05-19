<?php

namespace App\Modules\Loyalty\Contracts;

interface OCRServiceInterface {
    public function extractText(string $filePath): array;
}