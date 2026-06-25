<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = 'admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        // RateLimiter::for('qrscan', function ($request) {
        //     $userId = optional($request->user())->id;

        //     return [
        //         Limit::perMinute(1000)->by("user:$userId"),
        //         Limit::perSeconds(1, 20)->by("user:$userId") // burst control
        //     ];
        // });

        // RateLimiter::for('qrscan_ip', function ($request) {
        //     return Limit::perMinute(2000)->by("ip:".$request->ip());
        // });
    }
}
