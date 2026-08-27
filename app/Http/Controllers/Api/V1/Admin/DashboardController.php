<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->can('viewAny', Booking::class) || abort(403);
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();
        $completed = BookingStatus::Completed->value;

        return response()->json(['data' => [
            'counts' => [
                'today' => Booking::query()->whereDate('booking_date', $today)->count(),
                'upcoming' => Booking::query()->blockingAvailability()->whereDate('booking_date', '>=', $today)->count(),
                'pending' => Booking::query()->where('booking_status', BookingStatus::Pending)->count(),
                'completed' => Booking::query()->where('booking_status', $completed)->count(),
            ],
            'revenue' => [
                'total_minor' => (int) Booking::query()->where('booking_status', $completed)->sum('total_minor'),
                'month_minor' => (int) Booking::query()->where('booking_status', $completed)->where('created_at', '>=', $monthStart)->sum('total_minor'),
                'currency' => config('app.currency', 'EUR'),
            ],
            'top_tours' => Tour::query()->withCount('bookings')->orderByDesc('bookings_count')->limit(5)->get()
                ->map(fn (Tour $tour): array => ['id' => $tour->id, 'slug' => $tour->slug, 'bookings_count' => $tour->bookings_count])->values(),
            'top_cars' => Car::query()->withCount('bookings')->orderByDesc('bookings_count')->limit(5)->get()
                ->map(fn (Car $car): array => ['id' => $car->id, 'name' => "{$car->brand} {$car->model}", 'bookings_count' => $car->bookings_count])->values(),
        ]]);
    }
}
