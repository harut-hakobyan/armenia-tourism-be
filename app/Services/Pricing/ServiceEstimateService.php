<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Contracts\RouteCalculationService;
use App\Data\PriceBreakdown;
use App\Data\RoutePoint;
use App\Data\RouteResult;
use App\Enums\ServiceType;
use App\Models\Car;
use App\Models\Tour;
use Carbon\CarbonImmutable;

final class ServiceEstimateService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly RouteCalculationService $routing,
    ) {}

    /** @return array<string, mixed> */
    public function tour(
        Tour $tour,
        Car $car,
        int $passengers,
        CarbonImmutable $date,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): array {
        $price = $this->pricing->calculateTour($tour, $car, $passengers, $date, $promoCode, $customerEmail);

        return [
            'service_type' => ServiceType::Tour->value,
            'tour' => ['id' => $tour->id, 'slug' => $tour->slug],
            'tour_format' => $tour->format->value,
            'car' => ['id' => $car->id, 'name' => "{$car->brand} {$car->model}"],
            'booking_date' => $date->toDateString(),
            'starts_at' => $tour->start_time
                ? $date->setTimeFromTimeString((string) $tour->start_time)->toIso8601String()
                : null,
            'meeting_point' => $tour->meeting_point,
            'passengers' => $passengers,
            'duration_minutes' => $tour->duration_minutes,
            'pricing_type' => $tour->pricing_type->value,
            'price' => $price->toArray(),
        ];
    }

    /** @param list<RoutePoint> $points
     * @return array<string, mixed>
     */
    public function transfer(
        Car $car,
        array $points,
        int $passengers,
        int $extraWaitingMinutes = 0,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): array {
        $route = $this->routing->calculateRoute($points);
        $price = $this->pricing->calculateTransfer(
            $car,
            $route->distanceMeters,
            $passengers,
            $promoCode,
            $customerEmail,
        );

        return $this->routeEstimate(
            ServiceType::AirportTransfer,
            $car,
            $passengers,
            $route,
            $price,
            $route->drivingDurationMinutes + $extraWaitingMinutes,
            ['extra_waiting_minutes' => $extraWaitingMinutes],
        );
    }

    /** @return array<string, mixed> */
    public function privateDriver(
        Car $car,
        int $durationMinutes,
        int $passengers,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): array {
        $price = $this->pricing->calculatePrivateDriver(
            $car,
            $durationMinutes,
            $passengers,
            $promoCode,
            $customerEmail,
        );

        return [
            'service_type' => ServiceType::PrivateDriver->value,
            'car' => ['id' => $car->id, 'name' => "{$car->brand} {$car->model}"],
            'passengers' => $passengers,
            'duration_minutes' => $durationMinutes,
            'package_code' => match ($durationMinutes) {
                240 => '4_hours',
                480 => '8_hours',
                720 => '12_hours',
                1440 => 'full_day',
                default => 'custom',
            },
            'price' => $price->toArray(),
        ];
    }

    /** @param list<RoutePoint> $points
     * @return array<string, mixed>
     */
    public function customTrip(
        Car $car,
        array $points,
        int $passengers,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): array {
        $route = $this->routing->calculateRoute($points);
        $price = $this->routing->calculateEstimatedPrice(
            $route,
            $car,
            $passengers,
            $promoCode,
            $customerEmail,
        );

        return $this->routeEstimate(
            ServiceType::CustomTrip,
            $car,
            $passengers,
            $route,
            $price,
            $route->estimatedTourDurationMinutes,
        );
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function routeEstimate(
        ServiceType $type,
        Car $car,
        int $passengers,
        RouteResult $route,
        PriceBreakdown $price,
        int $durationMinutes,
        array $extra = [],
    ): array {
        return [
            'service_type' => $type->value,
            'car' => ['id' => $car->id, 'name' => "{$car->brand} {$car->model}"],
            'passengers' => $passengers,
            'estimated_distance_meters' => $route->distanceMeters,
            'estimated_driving_minutes' => $route->drivingDurationMinutes,
            'estimated_duration_minutes' => $durationMinutes,
            'route_provider' => $route->provider,
            'route_points' => array_map(static fn (RoutePoint $point): array => [
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'label' => $point->label,
            ], $route->points),
            'price' => $price->toArray(),
            ...$extra,
        ];
    }
}
