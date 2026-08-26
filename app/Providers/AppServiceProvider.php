<?php

namespace App\Providers;

use App\Contracts\BookingNotificationService;
use App\Contracts\RouteCalculationService;
use App\Services\Notifications\LaravelBookingNotificationService;
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
        $this->app->bind(BookingNotificationService::class, LaravelBookingNotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
