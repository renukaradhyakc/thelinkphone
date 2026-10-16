<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Services\NotificationRegistry;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(NotificationRegistry::class);
        $this->app->singleton(\App\Modules\Notifications\Services\TextBuilder::class);
        $this->app->singleton(\App\Modules\Notifications\Services\LinkResolver::class);
        $this->app->singleton(\App\Modules\Notifications\Services\RecipientResolver::class);
        $this->app->singleton(\App\Modules\Notifications\Services\DeviceService::class);
        $this->app->singleton(\App\Modules\Notifications\Services\NotificationService::class);
        $this->app->singleton(\App\Modules\Notifications\Services\BookingNotifier::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Notifications\Console\TestNotificationCommand::class,
                \App\Modules\Notifications\Console\PruneNotificationsCommand::class,
                \App\Modules\Notifications\Console\DispatchDueCommand::class,
                \App\Modules\Notifications\Console\TickCommand::class,
            ]);
        }
    }
}