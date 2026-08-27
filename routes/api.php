<?php

use App\Http\Controllers\Api\V1\Admin\AssignmentAvailabilityController;
use App\Http\Controllers\Api\V1\Admin\BookingOperationsController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DirectoryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Driver\TripController;
use App\Http\Controllers\Api\V1\PublicApi\CarController;
use App\Http\Controllers\Api\V1\PublicApi\DestinationController;
use App\Http\Controllers\Api\V1\PublicApi\EstimateController;
use App\Http\Controllers\Api\V1\PublicApi\TourCategoryController;
use App\Http\Controllers\Api\V1\PublicApi\TourController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): array => [
        'status' => 'ok',
        'service' => config('app.name'),
    ]);

    Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bookings/{bookingNumber}/{token}', [BookingController::class, 'show'])
        ->where('bookingNumber', 'AMT-\d{4}-\d{6}')
        ->middleware('throttle:30,1');

    Route::middleware('api.locale')->group(function (): void {
        Route::get('/destinations', [DestinationController::class, 'index']);
        Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show']);
        Route::get('/tour-categories', [TourCategoryController::class, 'index']);
        Route::get('/tour-categories/{category:slug}/tours', [TourController::class, 'category']);
        Route::get('/tour-categories/{category:slug}', [TourCategoryController::class, 'show']);
        Route::get('/tours', [TourController::class, 'index']);
        Route::get('/tours/{tour:slug}', [TourController::class, 'show']);
        Route::get('/cars', [CarController::class, 'index']);
        Route::get('/cars/{car}', [CarController::class, 'show']);

        Route::middleware('throttle:30,1')->group(function (): void {
            Route::post('/pricing/tours/estimate', [EstimateController::class, 'tour']);
            Route::post('/transfers/estimate', [EstimateController::class, 'transfer']);
            Route::post('/private-driver/estimate', [EstimateController::class, 'privateDriver']);
            Route::post('/custom-trips/estimate', [EstimateController::class, 'customTrip']);
        });
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::get('/admin/health', static fn (): array => ['status' => 'ok'])
        ->middleware(['auth:sanctum', 'role:admin']);

    Route::middleware(['auth:sanctum', 'role:admin,manager'])->prefix('admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/directory/tours', [DirectoryController::class, 'tours']);
        Route::get('/directory/destinations', [DirectoryController::class, 'destinations']);
        Route::get('/directory/cars', [DirectoryController::class, 'cars']);
        Route::get('/directory/drivers', [DirectoryController::class, 'drivers']);
        Route::patch('/directory/{type}/{id}', [DirectoryController::class, 'update'])
            ->whereIn('type', ['tours', 'destinations', 'cars', 'drivers']);
        Route::get('/bookings/calendar', [BookingOperationsController::class, 'calendar']);
        Route::get('/bookings', [BookingOperationsController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingOperationsController::class, 'show']);
        Route::get('/bookings/{booking}/availability', AssignmentAvailabilityController::class);
        Route::post('/bookings/{booking}/confirm', [BookingOperationsController::class, 'confirm']);
        Route::post('/bookings/{booking}/assign', [BookingOperationsController::class, 'assign']);
        Route::post('/bookings/{booking}/status', [BookingOperationsController::class, 'status']);
        Route::post('/bookings/{booking}/cancel', [BookingOperationsController::class, 'cancel']);
    });

    Route::middleware(['auth:sanctum', 'role:driver'])->prefix('driver')->group(function (): void {
        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/{booking}', [TripController::class, 'show']);
        Route::post('/trips/{booking}/status', [TripController::class, 'status']);
    });
});
