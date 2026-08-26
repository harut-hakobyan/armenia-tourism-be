<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Models\Car;
use App\Models\Driver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AvailabilityService
{
    public function isCarAvailable(Car|int $car, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreBookingId = null): bool
    {
        $this->validateWindow($startsAt, $endsAt);
        $carId = $car instanceof Car ? $car->id : $car;

        return Car::query()
            ->whereKey($carId)
            ->where('active', true)
            ->where('available_for_booking', true)
            ->whereDoesntHave('bookings', fn (Builder $query): Builder => $this->conflicting($query, $startsAt, $endsAt, $ignoreBookingId))
            ->exists();
    }

    public function isDriverAvailable(Driver|int $driver, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreBookingId = null): bool
    {
        $this->validateWindow($startsAt, $endsAt);
        $driverId = $driver instanceof Driver ? $driver->id : $driver;

        return Driver::query()
            ->whereKey($driverId)
            ->where('active', true)
            ->whereDoesntHave('bookings', fn (Builder $query): Builder => $this->conflicting($query, $startsAt, $endsAt, $ignoreBookingId))
            ->exists();
    }

    /** @return Collection<int, Car> */
    public function getAvailableCars(CarbonInterface $startsAt, CarbonInterface $endsAt, int $passengers = 1): Collection
    {
        $this->validateWindow($startsAt, $endsAt);

        if ($passengers < 1) {
            throw new InvalidArgumentException('Passenger count must be at least one.');
        }

        return Car::query()
            ->where('active', true)
            ->where('available_for_booking', true)
            ->where('passenger_capacity', '>=', $passengers)
            ->whereDoesntHave('bookings', fn (Builder $query): Builder => $this->conflicting($query, $startsAt, $endsAt))
            ->orderBy('base_price_minor')
            ->get();
    }

    /** @return Collection<int, Driver> */
    public function getAvailableDrivers(CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $carId = null): Collection
    {
        $this->validateWindow($startsAt, $endsAt);

        return Driver::query()
            ->where('active', true)
            ->when($carId, fn (Builder $query, int $id): Builder => $query->whereHas('cars', fn (Builder $cars): Builder => $cars->whereKey($id)))
            ->whereDoesntHave('bookings', fn (Builder $query): Builder => $this->conflicting($query, $startsAt, $endsAt))
            ->orderByDesc('rating')
            ->get();
    }

    private function conflicting(
        Builder $query,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreBookingId = null,
    ): Builder {
        return $query
            ->blockingAvailability()
            ->when($ignoreBookingId, fn (Builder $bookingQuery, int $id): Builder => $bookingQuery->whereKeyNot($id))
            ->where('starts_at', '<', $endsAt)
            ->where('planned_end_at', '>', $startsAt);
    }

    private function validateWindow(CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Availability end time must be after its start time.');
        }
    }
}
