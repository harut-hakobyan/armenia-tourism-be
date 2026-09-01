<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BookingCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_qr_and_manager_can_record_partial_and_complete_arrivals_idempotently(): void
    {
        Notification::fake();
        $this->seed();
        [$booking, $qrPayload] = $this->createBooking(3);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);

        $this->assertStringStartsWith('AMT-CHECKIN:', $qrPayload);
        $this->assertStringNotContainsString($booking->booking_number, $qrPayload);
        $this->actingAs($manager)->postJson('/api/v1/check-ins/lookup', ['token' => $qrPayload])
            ->assertOk()
            ->assertJsonPath('data.booking_number', $booking->booking_number)
            ->assertJsonPath('data.attendance.status', AttendanceStatus::Expected->value)
            ->assertJsonPath('data.attendance.remaining_passengers', 3);

        $this->actingAs($manager)->postJson('/api/v1/check-ins', [
            'token' => $qrPayload,
            'passengers' => 1,
            'notes' => 'First guest arrived.',
        ])->assertOk()
            ->assertJsonPath('data.attendance.status', AttendanceStatus::PartiallyCheckedIn->value)
            ->assertJsonPath('data.attendance.checked_in_passengers', 1)
            ->assertJsonCount(1, 'data.check_ins');

        $this->actingAs($manager)->postJson('/api/v1/check-ins', [
            'token' => $qrPayload,
            'passengers' => 2,
        ])->assertOk()
            ->assertJsonPath('data.attendance.status', AttendanceStatus::CheckedIn->value)
            ->assertJsonPath('data.attendance.checked_in_passengers', 3)
            ->assertJsonCount(2, 'data.check_ins');

        $this->actingAs($manager)->postJson('/api/v1/check-ins', [
            'token' => $qrPayload,
            'passengers' => 1,
        ])->assertOk()->assertJsonCount(2, 'data.check_ins');

        $this->assertDatabaseCount('booking_check_ins', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.checked_in', 'subject_id' => $booking->id]);
    }

    public function test_only_the_assigned_driver_can_lookup_and_check_in_the_booking(): void
    {
        Notification::fake();
        $this->seed();
        [$booking, $qrPayload] = $this->createBooking(2);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $assignedDriver = Driver::query()->where('email', 'arman.driver@armeniatourism.local')->firstOrFail();
        $otherDriver = Driver::query()->whereKeyNot($assignedDriver->id)->firstOrFail();

        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/confirm")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/assign", [
            'car_id' => $car->id,
            'driver_id' => $assignedDriver->id,
        ])->assertOk();

        $this->actingAs($otherDriver->user)->postJson('/api/v1/check-ins/lookup', ['token' => $qrPayload])
            ->assertForbidden();
        $this->actingAs($assignedDriver->user)->postJson('/api/v1/check-ins/lookup', ['token' => $qrPayload])
            ->assertOk()->assertJsonPath('data.id', $booking->id);
        $this->actingAs($assignedDriver->user)->postJson('/api/v1/check-ins', [
            'token' => $qrPayload,
            'passengers' => 2,
        ])->assertOk()->assertJsonPath('data.attendance.status', AttendanceStatus::CheckedIn->value);
    }

    public function test_invalid_and_cancelled_tickets_cannot_be_checked_in(): void
    {
        Notification::fake();
        $this->seed();
        [$booking, $qrPayload] = $this->createBooking();
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);

        $this->actingAs($manager)->postJson('/api/v1/check-ins/lookup', ['token' => 'AMT-CHECKIN:invalid'])
            ->assertNotFound();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/confirm")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/admin/bookings/{$booking->id}/cancel")->assertOk();
        $this->actingAs($manager)->postJson('/api/v1/check-ins', ['token' => $qrPayload, 'passengers' => 1])
            ->assertUnprocessable();
    }

    /** @return array{Booking, string} */
    private function createBooking(int $passengers = 1): array
    {
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $response = $this->postJson('/api/v1/bookings', [
            'idempotency_key' => (string) Str::uuid(),
            'service_type' => 'tour',
            'tour_id' => $tour->id,
            'car_id' => $car->id,
            'booking_date' => now()->addDays(30)->toDateString(),
            'pickup_time' => '09:00',
            'passengers' => $passengers,
            'pickup_address' => 'Republic Square, Yerevan',
            'customer_name' => 'QR Guest',
            'customer_email' => 'qr.guest@example.com',
            'customer_phone' => '+37499123456',
            'payment_method' => 'pay_driver',
        ])->assertCreated()->assertJsonStructure(['data' => ['secure_token', 'qr_payload', 'attendance']]);

        return [
            Booking::query()->where('booking_number', $response->json('data.booking_number'))->firstOrFail(),
            (string) $response->json('data.qr_payload'),
        ];
    }
}
