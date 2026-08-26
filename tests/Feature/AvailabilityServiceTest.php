<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CurrencyCode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Tour;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_car_and_driver_booking_is_rejected_but_touching_window_is_allowed(): void
    {
        $this->seed();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('preferred_car_id', $car->id)->firstOrFail();
        $booking = $this->createBooking($car, $driver);
        $availability = $this->app->make(AvailabilityService::class);

        $this->assertFalse($availability->isCarAvailable(
            $car,
            CarbonImmutable::parse('2026-09-12 10:00:00'),
            CarbonImmutable::parse('2026-09-12 12:00:00'),
        ));
        $this->assertFalse($availability->isDriverAvailable(
            $driver,
            CarbonImmutable::parse('2026-09-12 08:00:00'),
            CarbonImmutable::parse('2026-09-12 10:00:00'),
        ));
        $this->assertTrue($availability->isCarAvailable(
            $car,
            CarbonImmutable::parse('2026-09-12 17:00:00'),
            CarbonImmutable::parse('2026-09-12 19:00:00'),
        ));
        $this->assertTrue($availability->isCarAvailable(
            $car,
            CarbonImmutable::parse('2026-09-12 10:00:00'),
            CarbonImmutable::parse('2026-09-12 12:00:00'),
            $booking->id,
        ));
    }

    public function test_cancelled_booking_does_not_block_availability(): void
    {
        $this->seed();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('preferred_car_id', $car->id)->firstOrFail();
        $booking = $this->createBooking($car, $driver);
        $booking->update(['booking_status' => BookingStatus::Cancelled]);

        $availability = $this->app->make(AvailabilityService::class);

        $this->assertTrue($availability->isCarAvailable(
            $car,
            CarbonImmutable::parse('2026-09-12 10:00:00'),
            CarbonImmutable::parse('2026-09-12 12:00:00'),
        ));
        $this->assertTrue($availability->isDriverAvailable(
            $driver,
            CarbonImmutable::parse('2026-09-12 10:00:00'),
            CarbonImmutable::parse('2026-09-12 12:00:00'),
        ));
    }

    public function test_available_collections_filter_capacity_and_conflicts(): void
    {
        $this->seed();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('preferred_car_id', $car->id)->firstOrFail();
        $this->createBooking($car, $driver);
        $availability = $this->app->make(AvailabilityService::class);
        $startsAt = CarbonImmutable::parse('2026-09-12 10:00:00');
        $endsAt = CarbonImmutable::parse('2026-09-12 12:00:00');

        $cars = $availability->getAvailableCars($startsAt, $endsAt, 5);
        $drivers = $availability->getAvailableDrivers($startsAt, $endsAt, $car->id);

        $this->assertFalse($cars->contains($car));
        $this->assertTrue($cars->every(fn (Car $availableCar): bool => $availableCar->passenger_capacity >= 5));
        $this->assertFalse($drivers->contains($driver));
    }

    private function createBooking(Car $car, Driver $driver): Booking
    {
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();

        return Booking::query()->create([
            'booking_number' => 'AMT-2026-000001',
            'secure_token_hash' => hash('sha256', Str::random(64)),
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'availability-test'),
            'tour_id' => $tour->id,
            'car_id' => $car->id,
            'driver_id' => $driver->id,
            'service_type' => ServiceType::Tour,
            'booking_date' => '2026-09-12',
            'pickup_time' => '09:00:00',
            'starts_at' => '2026-09-12 09:00:00',
            'planned_end_at' => '2026-09-12 17:00:00',
            'pickup_address' => 'Republic Square, Yerevan',
            'passengers' => 3,
            'customer_name' => 'Test Guest',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '+37499123456',
            'subtotal_minor' => 7000,
            'discount_minor' => 0,
            'deposit_amount_minor' => 0,
            'total_minor' => 7000,
            'currency' => CurrencyCode::Eur,
            'payment_method' => PaymentMethod::PayDriver,
            'payment_status' => PaymentStatus::Unpaid,
            'booking_status' => BookingStatus::Confirmed,
        ]);
    }
}
