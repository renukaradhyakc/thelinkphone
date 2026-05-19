<?php

namespace App\Modules\Loyalty\Providers;

use Illuminate\Support\ServiceProvider;

use App\Modules\Loyalty\Contracts\OCRServiceInterface;

use App\Modules\Loyalty\Contracts\FraudServiceInterface;
use App\Modules\Loyalty\Services\Fraud\DuplicateFraudService;

use App\Modules\Loyalty\Contracts\PointsServiceInterface;
use App\Modules\Loyalty\Services\Points\DefaultPointsService;

use App\Modules\Loyalty\Services\OCR\OCRProviderRegistry;
use App\Modules\Loyalty\Services\OCR\TabScannerOCRService;
use App\Modules\Loyalty\Services\OCR\VeryfiOCRService;

use App\Modules\Loyalty\Services\Fraud\Engine\FraudDetectionEngine;
use App\Modules\Loyalty\Services\Fraud\Rules\SameAmountSameDateRule;
use App\Modules\Loyalty\Services\Fraud\Rules\HighFrequencyRule;
use App\Modules\Loyalty\Services\Fraud\Rules\SameVendorSpikeRule;
use App\Modules\Loyalty\Services\Fraud\Rules\OCRDuplicateSignalRule;
use App\Modules\Loyalty\Services\Fraud\FraudAuditService;

class LoyaltyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(OCRProviderRegistry::class, function ($app) {
            $registry = new OCRProviderRegistry();
            $registry->register('tabscanner', $app->make(TabScannerOCRService::class));
            $registry->register('veryfi', $app->make(VeryfiOCRService::class));
            return $registry;
        });

        $this->app->singleton(FraudDetectionEngine::class, function () {
            return new FraudDetectionEngine([
                new SameAmountSameDateRule(),
                new HighFrequencyRule(),
                new SameVendorSpikeRule(),
                new OCRDuplicateSignalRule(),
            ]);
        });

        $this->app->bind(
            FraudServiceInterface::class,
            DuplicateFraudService::class
        );

        $this->app->bind(
            PointsServiceInterface::class,
            DefaultPointsService::class
        );

        $this->app->singleton(FraudAuditService::class);
    }

    public function boot()
    {
        //
    }
}