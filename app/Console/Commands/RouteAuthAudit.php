<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class RouteAuthAudit extends Command
{
    protected $signature = 'route:audit';
    protected $description = 'List all routes and show which are auth-protected and public';

    public function handle()
    {
        $routes = Route::getRoutes();

        $authCount = 0;
        $publicCount = 0;

        $this->info("Listing all routes with middleware:\n");

        foreach ($routes as $route) {
            $uri = $route->uri();
            $middlewares = $route->middleware();
            $middlewareList = implode(', ', $middlewares);

            if (in_array('auth', $middlewares)) {
                $authCount++;
                $type = 'Auth-protected';
            } else {
                $publicCount++;
                $type = 'Public';
            }

            $this->line("[$type] $uri | Middleware: $middlewareList");
        }

        $this->info("\nSummary:");
        $this->info("Auth-protected routes: $authCount");
        $this->info("Public routes: $publicCount");
    }
}
