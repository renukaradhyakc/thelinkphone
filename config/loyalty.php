<?php

return [
    'ocr_providers' => explode(',', env('OCR_PROVIDERS', 'veryfi,tabscanner')),

    'fraud' => [
        'reject_threshold' => 90,
        'review_threshold' => 60,
    ]
];