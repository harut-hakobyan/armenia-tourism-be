<?php

namespace App\Providers;

use App\Contracts\RouteCalculationService;
use App\Services\Routing\HaversineRouteCalculationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RouteCalculationService::class, HaversineRouteCalculationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
