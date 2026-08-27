<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\DriverTripStatus;
use App\Exceptions\InvalidBookingStatusTransition;
use App\Models\Booking;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

final class DriverTripStatusTransitionService
{
    /** @var array<string, list<DriverTripStatus>> */
    private const ALLOWED_TRANSITIONS = [
        'assigned' => [DriverTripStatus::OnTheWay],
        'on_the_way' => [DriverTripStatus::Arrived],
        'arrived' => [DriverTripStatus::PassengerPickedUp],
        'passenger_picked_up' => [DriverTripStatus::TripStarted],
        'trip_started' => [DriverTripStatus::Completed],
        'completed' => [],
    ];

    public function __construct(private readonly BookingStatusTransitionService $bookingStatuses) {}

    public function transition(Booking $booking, Driver $driver, DriverTripStatus $toStatus, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $driver, $toStatus, $note): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->driver_id !== $driver->id) {
                throw new InvalidBookingStatusTransition('This trip is not assigned to the current driver.');
            }

            $fromStatus = $locked->driver_trip_status;

            if (! $fromStatus || ! in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus->value], true)) {
                throw new InvalidBookingStatusTransition('The requested driver trip status transition is not allowed.');
            }

            $locked->update(['driver_trip_status' => $toStatus]);
            $locked->driverTripStatusHistory()->create([
                'driver_id' => $driver->id,
                'user_id' => $driver->user_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
            ]);

            $bookingStatus = match ($toStatus) {
                DriverTripStatus::OnTheWay => BookingStatus::DriverOnTheWay,
                DriverTripStatus::Arrived => BookingStatus::DriverArrived,
                DriverTripStatus::TripStarted => BookingStatus::InProgress,
                DriverTripStatus::Completed => BookingStatus::Completed,
                DriverTripStatus::PassengerPickedUp, DriverTripStatus::Assigned => null,
            };

            if ($bookingStatus) {
                $locked = $this->bookingStatuses->transition(
                    $locked,
                    $bookingStatus,
                    $driver->user,
                    $note ?? "Driver status changed to {$toStatus->value}.",
                );
            }

            return $locked->load(['car', 'driver', 'statusHistory', 'driverTripStatusHistory']);
        }, 3);
    }
}
