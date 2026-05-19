<?php

return [
    'ocr_providers' => explode(',', env('OCR_PROVIDERS', 'tabscanner,veryfi')),

    'fraud' => [
        'reject_threshold' => 90,
        'review_threshold' => 60,
    ]
];