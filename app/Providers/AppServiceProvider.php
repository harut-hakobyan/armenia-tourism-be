<?php

namespace App\Providers;

use App\Contracts\BookingNotificationService;
use App\Contracts\RouteCalculationService;
use App\Contracts\TelegramBotClient;
use App\Services\Notifications\LaravelBookingNotificationService;
use App\Services\Routing\HaversineRouteCalculationService;
use App\Services\Telegram\HttpTelegramBotClient;
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
        $this->app->bind(TelegramBotClient::class, HttpTelegramBotClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
