<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\BookingNotificationService;
use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingStatusTransition;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\GroupTourDeparture;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\AdminNewBookingNotification;
use App\Notifications\CustomerBookingConfirmationNotification;
use App\Services\Booking\BookingStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_authoritatively_priced_tour_booking_and_view_it_with_secure_token(): void
    {
        $this->seed();
        $payload = $this->tourPayload();
        $payload['total'] = 1; // Untrusted client prices are intentionally ignored.

        $response = $this->postJson('/api/v1/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.service_type', 'tour')
            ->assertJsonPath('data.booking_status', 'pending')
            ->assertJsonPath('data.price.subtotal_minor', 7000)
            ->assertJsonPath('data.price.discount_minor', 700)
            ->assertJsonPath('data.price.total_minor', 6300);

        $bookingNumber = $response->json('data.booking_number');
        $secureToken = $response->json('data.secure_token');

        $this->assertMatchesRegularExpression('/^AMT-\d{4}-000001$/', $bookingNumber);
        $this->assertNotEmpty($secureToken);
        $this->assertDatabaseHas('bookings', [
            'booking_number' => $bookingNumber,
            'customer_email' => 'guest@example.com',
            'total_minor' => 6300,
            'driver_id' => null,
        ]);
        $this->assertDatabaseCount('tour_booking_details', 1);
        $this->assertDatabaseHas('booking_status_history', [
            'from_status' => null,
            'to_status' => BookingStatus::Pending->value,
        ]);

        $this->getJson("/api/v1/bookings/{$bookingNumber}/{$secureToken}")
            ->assertOk()
            ->assertJsonPath('data.booking_number', $bookingNumber)
            ->assertJsonMissingPath('data.secure_token_hash');

        $this->getJson("/api/v1/bookings/{$bookingNumber}/invalid-token")->assertNotFound();
    }

    public function test_same_idempotent_request_returns_same_booking_and_changed_request_conflicts(): void
    {
        $this->seed();
        $payload = $this->tourPayload();

        $first = $this->postJson('/api/v1/bookings', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/bookings', $payload)->assertOk();

        $this->assertSame($first->json('data.booking_number'), $second->json('data.booking_number'));
        $this->assertSame($first->json('data.secure_token'), $second->json('data.secure_token'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_idempotency_keys', 1);

        $payload['passengers'] = 2;
        $this->postJson('/api/v1/bookings', $payload)->assertConflict();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_overlapping_booking_is_rejected_inside_transaction(): void
    {
        $this->seed();
        $payload = $this->tourPayload();
        $this->postJson('/api/v1/bookings', $payload)->assertCreated();

        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['customer_email'] = 'second@example.com';

        $this->postJson('/api/v1/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The selected car is no longer available for this time.');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_group_departure_accepts_multiple_seat_bookings_and_prevents_overselling(): void
    {
        $this->seed();
        $tour = Tour::query()->where('slug', 'garni-geghard-group-tour')->firstOrFail();
        $departure = GroupTourDeparture::query()->where('tour_id', $tour->id)->orderBy('starts_at')->firstOrFail();
        $payload = $this->basePayload('tour', $departure->car_id, $departure->starts_at->toDateString());
        unset($payload['car_id']);
        $payload['tour_id'] = $tour->id;
        $payload['group_tour_departure_id'] = $departure->id;
        $payload['passengers'] = 4;

        $this->postJson('/api/v1/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.price.total_minor', 10000);

        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['customer_email'] = 'second-group@example.com';
        $payload['passengers'] = 3;
        $this->postJson('/api/v1/bookings', $payload)->assertCreated();

        $payload['idempotency_key'] = (string) Str::uuid();
        $payload['customer_email'] = 'sold-out@example.com';
        $payload['passengers'] = 1;
        $this->postJson('/api/v1/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Not enough seats remain for this group departure.');

        $this->assertSame(7, (int) Booking::query()->where('group_tour_departure_id', $departure->id)->sum('passengers'));
    }

    public function test_transfer_private_driver_and_custom_trip_store_service_specific_snapshots(): void
    {
        $this->seed();
        $cars = Car::query()->orderBy('id')->get();
        $baseDate = now()->addDays(45);

        $transfer = $this->basePayload('airport_transfer', $cars[0]->id, $baseDate->toDateString());
        $transfer['dropoff_address'] = 'Marriott Yerevan';
        $transfer['route_points'] = $this->routePoints();
        $transfer['service_options'] = [
            'flight_number' => 'LH 1560',
            'arrival_at' => $baseDate->setTime(8, 30)->toIso8601String(),
            'airport_pickup_sign' => true,
            'pickup_sign_name' => 'John Smith',
            'child_seat' => true,
            'extra_waiting_minutes' => 30,
        ];
        $this->postJson('/api/v1/bookings', $transfer)->assertCreated();

        $privateDriver = $this->basePayload('private_driver', $cars[1]->id, $baseDate->addDay()->toDateString());
        $privateDriver['duration_minutes'] = 480;
        $privateDriver['service_options'] = ['desired_destinations' => ['Garni', 'Geghard']];
        $this->postJson('/api/v1/bookings', $privateDriver)->assertCreated();

        $customTrip = $this->basePayload('custom_trip', $cars[2]->id, $baseDate->addDays(2)->toDateString());
        $customTrip['route_points'] = $this->routePoints();
        $customTrip['service_options'] = ['return_to_yerevan' => true];
        $this->postJson('/api/v1/bookings', $customTrip)->assertCreated();

        $this->assertDatabaseCount('transfer_booking_details', 1);
        $this->assertDatabaseCount('private_driver_booking_details', 1);
        $this->assertDatabaseHas('private_driver_booking_details', ['package_code' => '8_hours']);
        $this->assertDatabaseCount('custom_trip_booking_details', 1);
        $this->assertDatabaseCount('custom_trip_stops', 3);
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_booking_status_transitions_are_validated_and_recorded(): void
    {
        $this->seed();
        $response = $this->postJson('/api/v1/bookings', $this->tourPayload())->assertCreated();
        $booking = Booking::query()->where('booking_number', $response->json('data.booking_number'))->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $driver = Driver::query()->where('active', true)->firstOrFail();
        $transitions = $this->app->make(BookingStatusTransitionService::class);

        $booking = $transitions->transition($booking, BookingStatus::Confirmed, $admin, 'Confirmed by admin.');
        $booking->update(['driver_id' => $driver->id]);
        $booking = $transitions->transition($booking, BookingStatus::Assigned, $admin, 'Driver assigned.');

        $this->assertSame(BookingStatus::Assigned, $booking->booking_status);
        $this->assertDatabaseCount('booking_status_history', 3);
        $this->assertDatabaseHas('booking_status_history', [
            'user_id' => $admin->id,
            'from_status' => 'confirmed',
            'to_status' => 'assigned',
        ]);

        $this->expectException(InvalidBookingStatusTransition::class);
        $transitions->transition($booking, BookingStatus::Completed, $admin);
    }

    public function test_booking_notification_service_notifies_admin_and_customer(): void
    {
        Notification::fake();
        $this->seed();
        $response = $this->postJson('/api/v1/bookings', $this->tourPayload())->assertCreated();
        $booking = Booking::query()->where('booking_number', $response->json('data.booking_number'))->firstOrFail();

        $this->app->make(BookingNotificationService::class)->sendBookingCreated($booking);

        Notification::assertSentOnDemand(AdminNewBookingNotification::class);
        Notification::assertSentOnDemand(CustomerBookingConfirmationNotification::class);
    }

    /** @return array<string, mixed> */
    private function tourPayload(): array
    {
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $payload = $this->basePayload('tour', $car->id, now()->addDays(30)->toDateString());
        $payload['tour_id'] = $tour->id;
        $payload['promo_code'] = 'WELCOME10';

        return $payload;
    }

    /** @return array<string, mixed> */
    private function basePayload(string $serviceType, int $carId, string $date): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'service_type' => $serviceType,
            'car_id' => $carId,
            'booking_date' => $date,
            'pickup_time' => '09:00',
            'passengers' => 1,
            'pickup_address' => 'Republic Square, Yerevan',
            'pickup_latitude' => 40.1776,
            'pickup_longitude' => 44.5126,
            'customer_name' => 'Test Guest',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '+37499123456',
            'customer_whatsapp' => '+37499123456',
            'customer_nationality' => 'German',
            'payment_method' => 'pay_driver',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function routePoints(): array
    {
        return [
            ['latitude' => 40.1473, 'longitude' => 44.3959, 'label' => 'Zvartnots Airport'],
            ['latitude' => 40.1129, 'longitude' => 44.7300, 'label' => 'Garni'],
            ['latitude' => 40.1776, 'longitude' => 44.5126, 'label' => 'Yerevan'],
        ];
    }
}
