<?php

use App\Http\Controllers\Api\V1\Admin\BookingOperationsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Driver\TripController;
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
        Route::get('/bookings/calendar', [BookingOperationsController::class, 'calendar']);
        Route::get('/bookings', [BookingOperationsController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingOperationsController::class, 'show']);
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
