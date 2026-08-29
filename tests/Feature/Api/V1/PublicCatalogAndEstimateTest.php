<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Car;
use App\Models\Destination;
use App\Models\GroupTourDeparture;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublicCatalogAndEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_is_localized_filtered_paginated_and_hides_inactive_records(): void
    {
        $this->seed();

        $this->getJson('/api/v1/destinations?locale=en&featured=true&per_page=5')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('data.0.locale', 'en')
            ->assertJsonPath('data.0.name', 'Yerevan');

        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.8')
            ->getJson('/api/v1/destinations/lake-sevan')
            ->assertOk()
            ->assertHeader('Content-Language', 'ru')
            ->assertJsonPath('data.locale', 'ru');

        $destination = Destination::query()->where('slug', 'lake-sevan')->firstOrFail();
        $destination->translations()->where('locale', 'hy')->delete();
        $this->getJson('/api/v1/destinations/lake-sevan?locale=hy')
            ->assertOk()
            ->assertHeader('Content-Language', 'hy')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.name', 'Lake Sevan');

        Destination::query()->where('slug', 'garni')->update(['active' => false]);
        $this->getJson('/api/v1/destinations/garni')->assertNotFound();
        $this->getJson('/api/v1/destinations?locale=invalid')->assertUnprocessable();
    }

    public function test_tour_category_itinerary_and_car_catalog_responses_support_booking_ui(): void
    {
        $this->seed();

        $this->getJson('/api/v1/tour-categories?locale=en')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.slug', 'historical')
            ->assertJsonPath('data.0.name', 'Historical');

        $this->getJson('/api/v1/tours?locale=en&category=historical&format=private&featured=true&sort=price_asc')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'garni-geghard')
            ->assertJsonPath('data.0.starting_price.pricing_type', 'per_car');

        $this->getJson('/api/v1/tours?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.format', 'group');

        $this->getJson('/api/v1/tour-categories/nature/tours?locale=en&format=private')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category.slug', 'nature');

        $this->getJson('/api/v1/tours/garni-geghard?locale=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Garni & Geghard Private Tour')
            ->assertJsonPath('data.category.slug', 'historical')
            ->assertJsonCount(5, 'data.itinerary')
            ->assertJsonPath('data.itinerary.1.destination.slug', 'garni');

        $groupTour = $this->getJson('/api/v1/tours/garni-geghard-group-tour?locale=en')
            ->assertOk()
            ->assertJsonPath('data.format', 'group')
            ->assertJsonPath('data.starting_price.pricing_type', 'per_person')
            ->assertJsonCount(6, 'data.upcoming_departures');
        $this->assertSame(7, $groupTour->json('data.upcoming_departures.0.remaining_seats'));

        $this->getJson('/api/v1/cars?passengers=7&luggage=5&sort=capacity_desc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mercedes-Benz Vito')
            ->assertJsonPath('data.0.rates.currency', 'EUR')
            ->assertJsonMissingPath('data.0.plate_number');

        $this->getJson('/api/v1/cars?child_seat=false')
            ->assertOk();
    }

    public function test_all_estimate_endpoints_return_server_authoritative_minor_unit_prices(): void
    {
        $this->seed();
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $date = now()->addDays(30)->toDateString();

        $tourEstimate = $this->postJson('/api/v1/pricing/tours/estimate', [
            'tour_id' => $tour->id,
            'car_id' => $car->id,
            'booking_date' => $date,
            'passengers' => 4,
            'promo_code' => 'WELCOME10',
            'customer_email' => 'guest@example.com',
        ])->assertOk()
            ->assertJsonPath('data.service_type', 'tour')
            ->assertJsonPath('data.pricing_type', 'per_car')
            ->assertJsonPath('data.price.subtotal_minor', 7000)
            ->assertJsonPath('data.price.total_minor', 6300);

        $transfer = $this->postJson('/api/v1/transfers/estimate', [
            'car_id' => $car->id,
            'passengers' => 3,
            'extra_waiting_minutes' => 30,
            'route_points' => $this->routePoints(),
        ])->assertOk()
            ->assertJsonPath('data.service_type', 'airport_transfer')
            ->assertJsonPath('data.route_provider', 'haversine')
            ->assertJsonPath('data.extra_waiting_minutes', 30);

        $this->assertGreaterThan(0, $transfer->json('data.estimated_distance_meters'));
        $this->assertGreaterThan(7000, $transfer->json('data.price.total_minor'));

        $this->postJson('/api/v1/private-driver/estimate', [
            'car_id' => $car->id,
            'duration_minutes' => 480,
            'passengers' => 4,
        ])->assertOk()
            ->assertJsonPath('data.service_type', 'private_driver')
            ->assertJsonPath('data.package_code', '8_hours')
            ->assertJsonPath('data.price.total_minor', 19800);

        $custom = $this->postJson('/api/v1/custom-trips/estimate', [
            'car_id' => $car->id,
            'passengers' => 3,
            'route_points' => $this->routePoints(),
        ])->assertOk()
            ->assertJsonPath('data.service_type', 'custom_trip')
            ->assertJsonPath('data.route_points.1.label', 'Garni');

        $this->assertGreaterThan(
            $custom->json('data.estimated_driving_minutes'),
            $custom->json('data.estimated_duration_minutes'),
        );
        $this->assertSame(6300, $tourEstimate->json('data.price.total_minor'));
    }

    public function test_group_departure_with_deleted_car_is_hidden_and_returns_validation_error(): void
    {
        $this->seed();
        $tour = Tour::query()->where('slug', 'garni-geghard-group-tour')->firstOrFail();
        $departure = GroupTourDeparture::query()->where('tour_id', $tour->id)->firstOrFail();
        $departure->car()->firstOrFail()->delete();

        $this->getJson("/api/v1/tours/{$tour->slug}?locale=en")
            ->assertOk()
            ->assertJsonCount(0, 'data.upcoming_departures');

        $this->postJson('/api/v1/pricing/tours/estimate', [
            'tour_id' => $tour->id,
            'group_tour_departure_id' => $departure->id,
            'booking_date' => $departure->starts_at->toDateString(),
            'passengers' => 2,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.group_tour_departure_id.0', 'The selected group departure does not have an available vehicle.');
    }

    public function test_estimate_matches_booking_and_invalid_capacity_is_a_safe_validation_error(): void
    {
        $this->seed();
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $date = now()->addDays(35)->toDateString();
        $selection = [
            'tour_id' => $tour->id,
            'car_id' => $car->id,
            'booking_date' => $date,
            'passengers' => 3,
            'promo_code' => 'WELCOME10',
            'customer_email' => 'parity@example.com',
        ];

        $estimate = $this->postJson('/api/v1/pricing/tours/estimate', $selection)
            ->assertOk()
            ->json('data.price.total_minor');

        $booking = $this->postJson('/api/v1/bookings', [
            ...$selection,
            'idempotency_key' => (string) Str::uuid(),
            'service_type' => 'tour',
            'pickup_time' => '09:00',
            'pickup_address' => 'Republic Square, Yerevan',
            'customer_name' => 'Parity Guest',
            'customer_phone' => '+37499123456',
            'payment_method' => 'pay_driver',
        ])->assertCreated()->json('data.price.total_minor');

        $this->assertSame($estimate, $booking);

        $this->postJson('/api/v1/pricing/tours/estimate', [
            ...$selection,
            'passengers' => 5,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Passenger count exceeds the selected car capacity.')
            ->assertJsonValidationErrors('estimate');

        $this->postJson('/api/v1/custom-trips/estimate', [
            'car_id' => $car->id,
            'passengers' => 2,
            'route_points' => [$this->routePoints()[0]],
        ])->assertUnprocessable()->assertJsonValidationErrors('route_points');
    }

    /** @return list<array<string, mixed>> */
    private function routePoints(): array
    {
        return [
            ['latitude' => 40.1872023, 'longitude' => 44.5152090, 'label' => 'Yerevan'],
            ['latitude' => 40.1128610, 'longitude' => 44.7299850, 'label' => 'Garni'],
            ['latitude' => 40.1872023, 'longitude' => 44.5152090, 'label' => 'Yerevan'],
        ];
    }
}
