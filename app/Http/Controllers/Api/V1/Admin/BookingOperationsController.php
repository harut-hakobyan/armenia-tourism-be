<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Booking\AssignBookingAction;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignBookingRequest;
use App\Http\Requests\Admin\CalendarBookingsRequest;
use App\Http\Requests\Admin\ListBookingsRequest;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\AdminBookingResource;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Queries\BookingQuery;
use App\Services\Booking\BookingStatusTransitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class BookingOperationsController extends Controller
{
    public function index(ListBookingsRequest $request, BookingQuery $bookings): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $results = $bookings->build($filters)
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return AdminBookingResource::collection($results);
    }

    public function calendar(CalendarBookingsRequest $request, BookingQuery $bookings): AnonymousResourceCollection
    {
        return AdminBookingResource::collection(
            $bookings->build($request->validated())->limit(1000)->get(),
        );
    }

    public function show(Booking $booking): AdminBookingResource
    {
        Gate::authorize('view', $booking);

        return new AdminBookingResource($this->loadDetails($booking));
    }

    public function assign(
        AssignBookingRequest $request,
        Booking $booking,
        AssignBookingAction $action,
    ): AdminBookingResource {
        $validated = $request->validated();
        $assigned = $action->execute(
            $booking,
            Car::query()->findOrFail($validated['car_id']),
            Driver::query()->findOrFail($validated['driver_id']),
            $request->user(),
            $validated['note'] ?? null,
        );

        return new AdminBookingResource($this->loadDetails($assigned));
    }

    public function confirm(
        Request $request,
        Booking $booking,
        BookingStatusTransitionService $statuses,
    ): AdminBookingResource {
        return $this->transition($request, $booking, BookingStatus::Confirmed, $statuses);
    }

    public function cancel(
        Request $request,
        Booking $booking,
        BookingStatusTransitionService $statuses,
    ): AdminBookingResource {
        return $this->transition($request, $booking, BookingStatus::Cancelled, $statuses);
    }

    public function status(
        UpdateBookingStatusRequest $request,
        Booking $booking,
        BookingStatusTransitionService $statuses,
    ): AdminBookingResource {
        $validated = $request->validated();
        $updated = $statuses->transition(
            $booking,
            BookingStatus::from($validated['status']),
            $request->user(),
            $validated['note'] ?? null,
            $request->ip(),
        );

        return new AdminBookingResource($this->loadDetails($updated));
    }

    private function transition(
        Request $request,
        Booking $booking,
        BookingStatus $status,
        BookingStatusTransitionService $statuses,
    ): AdminBookingResource {
        Gate::authorize('update', $booking);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $updated = $statuses->transition(
            $booking,
            $status,
            $request->user(),
            $validated['note'] ?? null,
            $request->ip(),
        );

        return new AdminBookingResource($this->loadDetails($updated));
    }

    private function loadDetails(Booking $booking): Booking
    {
        return $booking->load([
            'tour.translations', 'car', 'driver', 'promoCode', 'statusHistory',
            'driverTripStatusHistory', 'tourDetail', 'transferDetail',
            'privateDriverDetail', 'customTripDetail.stops',
        ]);
    }
}
