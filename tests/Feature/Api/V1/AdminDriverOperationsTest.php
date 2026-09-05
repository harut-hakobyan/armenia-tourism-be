<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\CurrencyCode;
use App\Enums\DriverTripStatus;
use App\Enums\GroupTourDepartureStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\CustomerDriverAssignedNotification;
use App\Notifications\DriverAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminDriverOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_edit_and_soft_delete_cars(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager)->getJson('/api/v1/admin/directory/car-category-prices')
            ->assertOk()
            ->assertJsonPath('data.6.category', 'bus');
        $this->actingAs($manager)->patchJson('/api/v1/admin/directory/car-category-prices/minivan', [
            'fixed_price_minor' => 22200,
            'currency' => 'EUR',
        ])->assertOk()->assertJsonPath('data.fixed_price_minor', 22200);
        $payload = [
            'brand' => 'Volkswagen', 'model' => 'Crafter', 'year' => 2025,
            'plate_number' => 'AMT-777', 'color' => 'Silver', 'category' => 'minivan',
            'passenger_capacity' => 12, 'luggage_capacity' => 10, 'transmission' => 'automatic',
            'air_conditioning' => true, 'wifi' => true, 'child_seat_available' => true,
            'base_price_minor' => 15000, 'price_per_km_minor' => 120,
            'price_per_hour_minor' => 2500, 'currency' => 'EUR',
            'active' => true, 'available_for_booking' => true,
        ];

        $created = $this->actingAs($manager)->postJson('/api/v1/admin/directory/cars', $payload)
            ->assertCreated()
            ->assertJsonPath('data.plate_number', 'AMT-777')
            ->assertJsonPath('data.base_price_minor', 22200)
            ->assertJsonPath('data.price_per_km_minor', 0)
            ->assertJsonPath('data.price_per_hour_minor', 0);
        $carId = (int) $created->json('data.id');

        $this->actingAs($manager)->patchJson("/api/v1/admin/directory/cars/{$carId}", [
            'color' => 'Black', 'passenger_capacity' => 14,
        ])->assertOk()->assertJsonPath('data.color', 'Black');
        $this->assertDatabaseHas('cars', ['id' => $carId, 'passenger_capacity' => 14]);

        $this->actingAs($manager)->deleteJson("/api/v1/admin/directory/cars/{$carId}")
            ->assertNoContent();
        $this->assertSoftDeleted('cars', ['id' => $carId]);
    }

    public function test_car_assigned_to_future_group_departures_cannot_be_deleted(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $car = Car::query()->firstOrFail();
        $tour = Tour::query()->where('format', 'group')->firstOrFail();
        $startsAt = now()->addDay();
        $tour->groupDepartures()->create([
            'car_id' => $car->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(8),
            'meeting_point' => 'Republic Square, Yerevan',
            'capacity' => $car->passenger_capacity,
            'price_per_person_minor' => 2500,
            'currency' => CurrencyCode::Eur,
            'status' => GroupTourDepartureStatus::Scheduled,
            'active' => true,
        ]);

        $this->actingAs($manager)->deleteJson("/api/v1/admin/directory/cars/{$car->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This car is assigned to future group tour departures. Reassign those departures before deleting it.');

        $this->assertNotSoftDeleted('cars', ['id' => $car->id]);
    }

    public function test_manager_can_create_edit_and_delete_driver_with_login_and_cars(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $cars = Car::query()->orderBy('id')->limit(2)->get();
        $payload = [
            'first_name' => 'Levon', 'last_name' => 'Sargsyan',
            'phone' => '+37499123456', 'email' => 'levon.driver@example.com',
            'password' => 'StrongPass123!', 'locale' => 'en',
            'languages' => ['hy', 'en'], 'experience_years' => 8,
            'license_number' => 'DRV-TEST-9001', 'rating' => 4.8,
            'active' => true, 'preferred_car_id' => $cars[0]->id,
            'car_ids' => $cars->pluck('id')->all(),
        ];

        $created = $this->actingAs($manager)->postJson('/api/v1/admin/directory/drivers', $payload)
            ->assertCreated()
            ->assertJsonPath('data.email', 'levon.driver@example.com')
            ->assertJsonPath('data.car_ids.0', $cars[0]->id);
        $driverId = (int) $created->json('data.id');
        $driver = Driver::query()->findOrFail($driverId);
        $this->assertDatabaseHas('users', ['id' => $driver->user_id, 'role' => UserRole::Driver->value, 'is_active' => true]);

        $this->actingAs($manager)->patchJson("/api/v1/admin/directory/drivers/{$driverId}", [
            'last_name' => 'Hakobyan', 'active' => false,
            'preferred_car_id' => $cars[1]->id, 'car_ids' => [$cars[1]->id],
        ])->assertOk()
            ->assertJsonPath('data.last_name', 'Hakobyan')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.preferred_car_id', $cars[1]->id);
        $this->assertDatabaseHas('users', ['id' => $driver->user_id, 'last_name' => 'Hakobyan', 'is_active' => false]);
        $this->assertDatabaseHas('driver_cars', ['driver_id' => $driverId, 'car_id' => $cars[1]->id]);

        $this->actingAs($manager)->deleteJson("/api/v1/admin/directory/drivers/{$driverId}")->assertNoContent();
        $this->assertSoftDeleted('drivers', ['id' => $driverId]);
        $this->assertDatabaseHas('users', ['id' => $driver->user_id, 'is_active' => false]);
    }

    public function test_operations_dashboard_directories_and_assignment_availability_are_available_to_managers(): void
    {
        $this->seed();
        $booking = $this->createTourBooking();
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonStructure(['data' => ['counts', 'revenue', 'top_tours', 'top_cars']]);

        foreach (['tours', 'destinations', 'cars', 'drivers'] as $directory) {
            $this->actingAs($manager)->getJson("/api/v1/admin/directory/{$directory}")
                ->assertOk()->assertJsonStructure(['data', 'current_page', 'total']);
        }

        $car = Car::query()->where('plate_number', 'AMT-601')->firstOrFail();
        $this->actingAs($manager)->patchJson("/api/v1/admin/directory/cars/{$car->id}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('cars', ['id' => $car->id, 'active' => false]);
        $car->refresh()->update(['active' => true]);

        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/confirm")->assertOk();
        $this->actingAs($manager)->getJson("/api/v1/admin/bookings/{$booking->id}/availability")
            ->assertOk()
            ->assertJsonCount(6, 'data.cars')
            ->assertJsonCount(2, 'data.drivers');
    }

    public function test_admin_and_manager_can_query_booking_operations_but_customers_cannot(): void
    {
        $this->seed();
        $booking = $this->createTourBooking();
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/bookings?booking_status=pending&search='.$booking->booking_number)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_number', $booking->booking_number);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/bookings/calendar?date_from='.$booking->booking_date->toDateString()
                .'&date_to='.$booking->booking_date->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($manager)
            ->getJson("/api/v1/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);

        $this->actingAs($customer)->getJson('/api/v1/admin/bookings')->assertForbidden();
    }

    public function test_manager_can_confirm_and_assign_an_authorized_available_driver_and_car(): void
    {
        Notification::fake();
        $this->seed();
        $booking = $this->createTourBooking();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('email', 'arman.driver@armeniatourism.local')->firstOrFail();

        $this->actingAs($manager)
            ->postJson("/api/v1/admin/bookings/{$booking->id}/confirm", ['note' => 'Confirmed.'])
            ->assertOk()
            ->assertJsonPath('data.booking_status', BookingStatus::Confirmed->value);

        $this->actingAs($manager)
            ->postJson("/api/v1/admin/bookings/{$booking->id}/assign", [
                'car_id' => $car->id,
                'driver_id' => $driver->id,
                'note' => 'Primary assignment.',
            ])
            ->assertOk()
            ->assertJsonPath('data.booking_status', BookingStatus::Assigned->value)
            ->assertJsonPath('data.driver_trip_status', DriverTripStatus::Assigned->value)
            ->assertJsonPath('data.driver.name', 'Arman Petrosyan');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'driver_id' => $driver->id,
            'driver_trip_status' => DriverTripStatus::Assigned->value,
        ]);
        $this->assertDatabaseHas('driver_trip_status_history', [
            'booking_id' => $booking->id,
            'to_status' => DriverTripStatus::Assigned->value,
            'user_id' => $manager->id,
        ]);
        Notification::assertSentOnDemand(DriverAssignedNotification::class);
        Notification::assertSentOnDemand(
            CustomerDriverAssignedNotification::class,
            fn ($notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $booking->customer_email,
        );
    }

    public function test_assignment_rejects_unauthorized_car_and_overlapping_driver(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $driver = Driver::query()->where('email', 'arman.driver@armeniatourism.local')->firstOrFail();
        $authorizedCar = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $secondCar = Car::query()->where('plate_number', 'AMT-301')->firstOrFail();
        $first = $this->createTourBooking($authorizedCar, now()->addDays(30)->toDateString());

        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$first->id}/confirm")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$first->id}/assign", [
            'car_id' => $secondCar->id,
            'driver_id' => $driver->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'The selected driver is not authorized to use this car.');

        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$first->id}/assign", [
            'car_id' => $authorizedCar->id,
            'driver_id' => $driver->id,
        ])->assertOk();

        $driver->cars()->syncWithoutDetaching([$secondCar->id]);
        $second = $this->createTourBooking($secondCar, $first->booking_date->toDateString(), 'second@example.com');
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$second->id}/confirm")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$second->id}/assign", [
            'car_id' => $secondCar->id,
            'driver_id' => $driver->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'The selected driver has an overlapping booking.');
    }

    public function test_driver_sees_only_assigned_trips_and_completes_the_controlled_workflow(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('email', 'arman.driver@armeniatourism.local')->firstOrFail();
        $otherDriver = Driver::query()->where('email', 'gor.driver@armeniatourism.local')->firstOrFail();
        $booking = $this->createTourBooking($car);

        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/confirm")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/assign", [
            'car_id' => $car->id,
            'driver_id' => $driver->id,
        ])->assertOk();

        $this->actingAs($otherDriver->user)
            ->getJson("/api/v1/driver/trips/{$booking->id}")
            ->assertForbidden();

        $this->actingAs($driver->user)
            ->getJson('/api/v1/driver/trips?status=assigned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.customer.phone', '+37499123456');

        $this->actingAs($driver->user)
            ->postJson("/api/v1/driver/trips/{$booking->id}/status", ['status' => 'arrived'])
            ->assertUnprocessable();

        foreach (['on_the_way', 'arrived', 'passenger_picked_up', 'trip_started', 'completed'] as $status) {
            $this->actingAs($driver->user)
                ->postJson("/api/v1/driver/trips/{$booking->id}/status", [
                    'status' => $status,
                    'note' => "Driver moved to {$status}.",
                ])
                ->assertOk()
                ->assertJsonPath('data.driver_trip_status', $status);
        }

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => BookingStatus::Completed->value,
            'driver_trip_status' => DriverTripStatus::Completed->value,
        ]);
        $this->assertDatabaseCount('driver_trip_status_history', 6);
        $this->assertDatabaseCount('booking_status_history', 7);
    }

    private function createTourBooking(
        ?Car $car = null,
        ?string $date = null,
        string $email = 'guest@example.com',
    ): Booking {
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car ??= Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $response = $this->postJson('/api/v1/bookings', [
            'idempotency_key' => (string) Str::uuid(),
            'service_type' => 'tour',
            'tour_id' => $tour->id,
            'car_id' => $car->id,
            'booking_date' => $date ?? now()->addDays(30)->toDateString(),
            'pickup_time' => '09:00',
            'passengers' => 1,
            'pickup_address' => 'Republic Square, Yerevan',
            'customer_name' => 'Test Guest',
            'customer_email' => $email,
            'customer_phone' => '+37499123456',
            'payment_method' => 'pay_driver',
        ])->assertCreated();

        return Booking::query()
            ->where('booking_number', $response->json('data.booking_number'))
            ->firstOrFail();
    }
}
