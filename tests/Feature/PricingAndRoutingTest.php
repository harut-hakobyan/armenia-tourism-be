<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\RouteCalculationService;
use App\Data\RoutePoint;
use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Exceptions\PromotionException;
use App\Models\Car;
use App\Models\PromoCode;
use App\Models\Tour;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\PromotionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PricingAndRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_car_tour_price_does_not_multiply_by_passenger_count(): void
    {
        $this->seed();
        $pricing = $this->app->make(PricingService::class);
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();

        $onePassenger = $pricing->calculateTour($tour, $car, 1, CarbonImmutable::parse('2026-09-12'));
        $fourPassengers = $pricing->calculateTour($tour, $car, 4, CarbonImmutable::parse('2026-09-12'));

        $this->assertSame(7000, $onePassenger->totalMinor);
        $this->assertSame($onePassenger->totalMinor, $fourPassengers->totalMinor);
    }

    public function test_tour_car_modifier_and_percentage_promotion_are_applied_server_side(): void
    {
        $this->seed();
        $pricing = $this->app->make(PricingService::class);
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-401')->firstOrFail();

        $price = $pricing->calculateTour(
            $tour,
            $car,
            3,
            CarbonImmutable::parse('2026-09-12'),
            'welcome10',
            'guest@example.com',
        );

        $this->assertSame(7000, $price->baseMinor);
        $this->assertSame(4000, $price->adjustments['car_category']);
        $this->assertSame(11000, $price->subtotalMinor);
        $this->assertSame(1100, $price->discountMinor);
        $this->assertSame(9900, $price->totalMinor);
    }

    public function test_per_person_pricing_is_supported_only_when_configured_on_tour(): void
    {
        $this->seed();
        $pricing = $this->app->make(PricingService::class);
        $tour = Tour::query()->create([
            'slug' => 'per-person-example',
            'duration_minutes' => 120,
            'starting_price_minor' => 2000,
            'currency' => 'EUR',
            'pricing_type' => PricingType::PerPerson,
            'active' => true,
            'max_passengers' => 4,
        ]);
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();

        $price = $pricing->calculateTour($tour, $car, 3, CarbonImmutable::parse('2026-09-12'));

        $this->assertSame(6000, $price->totalMinor);
    }

    public function test_custom_trip_uses_integer_distance_and_duration_components(): void
    {
        $this->seed();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();

        $price = $this->app->make(PricingService::class)
            ->calculateCustomTrip($car, 100_000, 180);

        $this->assertSame(7000, $price->baseMinor);
        $this->assertSame(5500, $price->adjustments['distance']);
        $this->assertSame(4800, $price->adjustments['duration']);
        $this->assertSame(17300, $price->totalMinor);
    }

    public function test_route_provider_calculates_route_and_delegates_authoritative_price(): void
    {
        $this->seed();
        $routing = $this->app->make(RouteCalculationService::class);
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $route = $routing->calculateRoute([
            new RoutePoint(40.1872023, 44.5152090, 'Yerevan'),
            new RoutePoint(40.1128610, 44.7299850, 'Garni'),
            new RoutePoint(40.1872023, 44.5152090, 'Yerevan'),
        ]);
        $price = $routing->calculateEstimatedPrice($route, $car);

        $this->assertSame('haversine', $route->provider);
        $this->assertGreaterThan(40_000, $route->distanceMeters);
        $this->assertGreaterThan($route->drivingDurationMinutes, $route->estimatedTourDurationMinutes);
        $this->assertGreaterThan($car->base_price_minor, $price->totalMinor);
    }

    public function test_promotion_minimum_order_is_enforced(): void
    {
        $this->seed();
        $promotions = $this->app->make(PromotionService::class);

        $this->expectException(PromotionException::class);
        $promotions->calculateDiscount(
            PromoCode::query()->where('code', 'ARMENIA25')->firstOrFail()->code,
            10000,
            CurrencyCode::Eur,
            'guest@example.com',
        );
    }

    public function test_car_capacity_rejects_too_many_passengers(): void
    {
        $this->seed();
        $tour = Tour::query()->where('slug', 'garni-geghard')->firstOrFail();
        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(PricingService::class)
            ->calculateTour($tour, $car, 5, CarbonImmutable::parse('2026-09-12'));
    }
}
