<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Services\Availability\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class AssignmentAvailabilityController extends Controller
{
    public function __invoke(Booking $booking, AvailabilityService $availability): JsonResponse
    {
        Gate::authorize('assign', $booking);
        $cars = Car::query()->where('active', true)->where('available_for_booking', true)
            ->where('passenger_capacity', '>=', $booking->passengers)->get()
            ->filter(fn (Car $car): bool => $availability->isCarAvailable($car, $booking->starts_at, $booking->planned_end_at, $booking->id));
        $carIds = $cars->pluck('id');
        $drivers = Driver::query()->where('active', true)->with('cars:id,brand,model')
            ->whereHas('cars', fn ($query) => $query->whereIn('cars.id', $carIds))
            ->get()->filter(fn (Driver $driver): bool => $availability->isDriverAvailable($driver, $booking->starts_at, $booking->planned_end_at, $booking->id));

        return response()->json(['data' => [
            'cars' => $cars->map(fn (Car $car): array => [
                'id' => $car->id, 'name' => "{$car->brand} {$car->model}", 'plate_number' => $car->plate_number,
                'category' => $car->category->value, 'passenger_capacity' => $car->passenger_capacity,
            ])->values(),
            'drivers' => $drivers->map(fn (Driver $driver): array => [
                'id' => $driver->id, 'name' => "{$driver->first_name} {$driver->last_name}", 'phone' => $driver->phone,
                'rating' => $driver->rating, 'car_ids' => $driver->cars->pluck('id')->values(),
            ])->values(),
        ]]);
    }
}
