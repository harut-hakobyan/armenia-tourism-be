<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DriverTripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverTripStatusRequest;
use App\Http\Resources\DriverBookingResource;
use App\Models\Booking;
use App\Models\Driver;
use App\Services\Booking\DriverTripStatusTransitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class TripController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(DriverTripStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $driver = $this->driver($request);
        $bookings = Booking::query()
            ->where('driver_id', $driver->id)
            ->with(['tour.translations', 'car', 'driver', 'statusHistory', 'driverTripStatusHistory'])
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('booking_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('booking_date', '<=', $date))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('driver_trip_status', $status))
            ->orderBy('starts_at')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return DriverBookingResource::collection($bookings);
    }

    public function show(Request $request, Booking $booking): DriverBookingResource
    {
        Gate::authorize('view', $booking);

        return new DriverBookingResource($this->loadDetails($booking));
    }

    public function status(
        UpdateDriverTripStatusRequest $request,
        Booking $booking,
        DriverTripStatusTransitionService $statuses,
    ): DriverBookingResource {
        $validated = $request->validated();
        $updated = $statuses->transition(
            $booking,
            $this->driver($request),
            DriverTripStatus::from($validated['status']),
            $validated['note'] ?? null,
        );

        return new DriverBookingResource($this->loadDetails($updated));
    }

    private function driver(Request $request): Driver
    {
        return $request->user()->driver ?? abort(403, 'Driver profile is required.');
    }

    private function loadDetails(Booking $booking): Booking
    {
        return $booking->load([
            'tour.translations', 'car', 'driver', 'statusHistory',
            'driverTripStatusHistory', 'tourDetail', 'transferDetail',
            'privateDriverDetail', 'customTripDetail.stops', 'checkIns.checkedInBy',
        ]);
    }
}
