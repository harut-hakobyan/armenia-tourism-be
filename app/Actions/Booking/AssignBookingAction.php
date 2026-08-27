<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\DriverTripStatus;
use App\Events\DriverAssigned;
use App\Exceptions\AssignmentException;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingStatusTransitionService;
use Illuminate\Support\Facades\DB;

final class AssignBookingAction
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingStatusTransitionService $statuses,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Booking $booking, Car $car, Driver $driver, User $actor, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $car, $driver, $actor, $note): Booking {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $lockedCar = Car::query()->lockForUpdate()->findOrFail($car->id);
            $lockedDriver = Driver::query()->lockForUpdate()->findOrFail($driver->id);

            if (! in_array($lockedBooking->booking_status, [BookingStatus::Confirmed, BookingStatus::Assigned], true)) {
                throw new AssignmentException('Only confirmed or already assigned bookings can be assigned.');
            }

            if (! $lockedCar->active || ! $lockedCar->available_for_booking || ! $lockedDriver->active) {
                throw new AssignmentException('The selected car or driver is inactive.');
            }

            if (! $lockedDriver->cars()->whereKey($lockedCar->id)->exists()) {
                throw new AssignmentException('The selected driver is not authorized to use this car.');
            }

            if (! $this->availability->isCarAvailable(
                $lockedCar,
                $lockedBooking->starts_at,
                $lockedBooking->planned_end_at,
                $lockedBooking->id,
            )) {
                throw new AssignmentException('The selected car has an overlapping booking.');
            }

            if (! $this->availability->isDriverAvailable(
                $lockedDriver,
                $lockedBooking->starts_at,
                $lockedBooking->planned_end_at,
                $lockedBooking->id,
            )) {
                throw new AssignmentException('The selected driver has an overlapping booking.');
            }

            $previousDriverStatus = $lockedBooking->driver_trip_status;
            $oldAssignment = $lockedBooking->only(['car_id', 'driver_id', 'driver_trip_status']);
            $lockedBooking->update([
                'car_id' => $lockedCar->id,
                'driver_id' => $lockedDriver->id,
                'driver_trip_status' => DriverTripStatus::Assigned,
            ]);
            $lockedBooking->driverTripStatusHistory()->create([
                'driver_id' => $lockedDriver->id,
                'user_id' => $actor->id,
                'from_status' => $previousDriverStatus,
                'to_status' => DriverTripStatus::Assigned,
                'note' => $note ?? 'Driver and car assigned.',
            ]);
            $this->audit->record($actor, 'booking.assigned', $lockedBooking, $oldAssignment, $lockedBooking->only(['car_id', 'driver_id', 'driver_trip_status']));

            if ($lockedBooking->booking_status === BookingStatus::Confirmed) {
                $lockedBooking = $this->statuses->transition(
                    $lockedBooking,
                    BookingStatus::Assigned,
                    $actor,
                    $note ?? 'Driver and car assigned.',
                );
            }

            DriverAssigned::dispatch($lockedBooking);

            return $lockedBooking->load(['car', 'driver', 'statusHistory', 'driverTripStatusHistory']);
        }, 3);
    }
}
