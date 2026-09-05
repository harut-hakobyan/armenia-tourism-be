<?php

use App\Http\Controllers\Api\V1\Admin\AssignmentAvailabilityController;
use App\Http\Controllers\Api\V1\Admin\BookingOperationsController;
use App\Http\Controllers\Api\V1\Admin\CmsController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DirectoryController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\Driver\TripController;
use App\Http\Controllers\Api\V1\PublicApi\CarController;
use App\Http\Controllers\Api\V1\PublicApi\ContentController;
use App\Http\Controllers\Api\V1\PublicApi\DestinationController;
use App\Http\Controllers\Api\V1\PublicApi\EstimateController;
use App\Http\Controllers\Api\V1\PublicApi\ReviewController;
use App\Http\Controllers\Api\V1\PublicApi\TourCategoryController;
use App\Http\Controllers\Api\V1\PublicApi\TourController;
use App\Http\Controllers\Api\V1\TelegramConnectionController;
use App\Http\Controllers\Api\V1\TelegramWebhookController;
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
        Route::get('/faqs', [ContentController::class, 'faqs']);
        Route::get('/settings', [ContentController::class, 'settings']);
        Route::get('/reviews', [ReviewController::class, 'index']);

        Route::middleware('throttle:30,1')->group(function (): void {
            Route::post('/pricing/tours/estimate', [EstimateController::class, 'tour']);
            Route::post('/transfers/estimate', [EstimateController::class, 'transfer']);
            Route::post('/private-driver/estimate', [EstimateController::class, 'privateDriver']);
            Route::post('/custom-trips/estimate', [EstimateController::class, 'customTrip']);
        });
    });

    Route::post('/contact-inquiries', [ContentController::class, 'contact'])->middleware('throttle:5,1');
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('throttle:5,1');

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

    Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:120,1');
    Route::middleware(['auth:sanctum', 'role:admin,manager,driver'])->prefix('telegram')->group(function (): void {
        Route::get('/', [TelegramConnectionController::class, 'show']);
        Route::post('/link', [TelegramConnectionController::class, 'link'])->middleware('throttle:5,1');
        Route::patch('/preferences', [TelegramConnectionController::class, 'preferences']);
        Route::delete('/', [TelegramConnectionController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:admin,manager,driver', 'throttle:60,1'])
        ->prefix('check-ins')->group(function (): void {
            Route::post('/lookup', [CheckInController::class, 'lookup']);
            Route::post('/', [CheckInController::class, 'store']);
        });

    Route::middleware(['auth:sanctum', 'role:admin,manager'])->prefix('admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/directory/tours', [DirectoryController::class, 'tours']);
        Route::post('/directory/tours', [DirectoryController::class, 'storeTour']);
        Route::patch('/directory/tours/{tour}', [DirectoryController::class, 'updateTour']);
        Route::delete('/directory/tours/{tour}', [DirectoryController::class, 'destroyTour']);
        Route::get('/directory/tour-categories', [DirectoryController::class, 'tourCategories']);
        Route::get('/directory/destinations', [DirectoryController::class, 'destinations']);
        Route::post('/directory/destinations', [DirectoryController::class, 'storeDestination']);
        Route::patch('/directory/destinations/{destination}', [DirectoryController::class, 'updateDestination']);
        Route::delete('/directory/destinations/{destination}', [DirectoryController::class, 'destroyDestination']);
        Route::get('/directory/cars', [DirectoryController::class, 'cars']);
        Route::get('/directory/car-category-prices', [DirectoryController::class, 'carCategoryPrices']);
        Route::patch('/directory/car-category-prices/{category}', [DirectoryController::class, 'updateCarCategoryPrice']);
        Route::post('/directory/cars', [DirectoryController::class, 'storeCar']);
        Route::delete('/directory/cars/{car}', [DirectoryController::class, 'destroyCar']);
        Route::get('/directory/drivers', [DirectoryController::class, 'drivers']);
        Route::post('/directory/drivers', [DirectoryController::class, 'storeDriver']);
        Route::patch('/directory/drivers/{driver}', [DirectoryController::class, 'updateDriver']);
        Route::delete('/directory/drivers/{driver}', [DirectoryController::class, 'destroyDriver']);
        Route::patch('/directory/{type}/{id}', [DirectoryController::class, 'update'])
            ->whereIn('type', ['cars', 'drivers']);
        Route::get('/bookings/calendar', [BookingOperationsController::class, 'calendar']);
        Route::get('/bookings', [BookingOperationsController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingOperationsController::class, 'show']);
        Route::get('/bookings/{booking}/availability', AssignmentAvailabilityController::class);
        Route::post('/bookings/{booking}/confirm', [BookingOperationsController::class, 'confirm']);
        Route::post('/bookings/{booking}/assign', [BookingOperationsController::class, 'assign']);
        Route::post('/bookings/{booking}/status', [BookingOperationsController::class, 'status']);
        Route::post('/bookings/{booking}/cancel', [BookingOperationsController::class, 'cancel']);
        Route::get('/customers', [CmsController::class, 'customers']);
        Route::get('/reviews', [CmsController::class, 'reviews']);
        Route::patch('/reviews/{review}', [CmsController::class, 'updateReview']);
        Route::get('/promo-codes', [CmsController::class, 'promoCodes']);
        Route::post('/promo-codes', [CmsController::class, 'storePromoCode']);
        Route::patch('/promo-codes/{promoCode}', [CmsController::class, 'updatePromoCode']);
        Route::get('/faqs', [CmsController::class, 'faqs']);
        Route::post('/faqs', [CmsController::class, 'storeFaq']);
        Route::patch('/faqs/{faq}', [CmsController::class, 'updateFaq']);
        Route::get('/inquiries', [CmsController::class, 'inquiries']);
        Route::patch('/inquiries/{inquiry}', [CmsController::class, 'updateInquiry']);
        Route::get('/media/{type}/{id}', [MediaController::class, 'index'])
            ->whereIn('type', ['tours', 'destinations', 'cars', 'drivers', 'tour-categories']);
        Route::post('/media/{type}/{id}', [MediaController::class, 'store'])
            ->whereIn('type', ['tours', 'destinations', 'cars', 'drivers', 'tour-categories']);
        Route::delete('/media/{media}', [MediaController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::patch('/settings/{setting}', [SettingsController::class, 'update']);
        Route::get('/audit-logs', [SettingsController::class, 'auditLogs']);
    });

    Route::middleware(['auth:sanctum', 'role:driver'])->prefix('driver')->group(function (): void {
        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/{booking}', [TripController::class, 'show']);
        Route::post('/trips/{booking}/status', [TripController::class, 'status']);
    });
});
