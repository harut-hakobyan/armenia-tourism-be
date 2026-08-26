<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Contracts\RouteCalculationService;
use App\Data\PriceBreakdown;
use App\Data\RoutePoint;
use App\Data\RouteResult;
use App\Models\Car;
use App\Services\Pricing\PricingService;
use InvalidArgumentException;

final class HaversineRouteCalculationService implements RouteCalculationService
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(private readonly PricingService $pricing) {}

    public function calculateDistance(array $points): int
    {
        $this->validatePoints($points);
        $distance = 0.0;

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $distance += $this->distanceBetween($points[$index - 1], $points[$index]);
        }

        return (int) round($distance * (float) config('tourism.routing.road_factor', 1.20));
    }

    public function calculateDuration(array $points): int
    {
        $distanceKm = $this->calculateDistance($points) / 1000;
        $averageSpeed = max(1, (int) config('tourism.routing.average_speed_kmh', 55));

        return (int) ceil(($distanceKm / $averageSpeed) * 60);
    }

    public function calculateRoute(array $points): RouteResult
    {
        $distance = $this->calculateDistance($points);
        $drivingDuration = $this->calculateDuration($points);
        $intermediateStops = max(0, count($points) - 2);
        $stopDuration = $intermediateStops * (int) config('tourism.routing.default_stop_minutes', 45);

        return new RouteResult(
            distanceMeters: $distance,
            drivingDurationMinutes: $drivingDuration,
            estimatedTourDurationMinutes: $drivingDuration + $stopDuration,
            points: $points,
            provider: 'haversine',
        );
    }

    public function calculateEstimatedPrice(
        RouteResult $route,
        Car $car,
        int $passengers = 1,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): PriceBreakdown {
        return $this->pricing->calculateCustomTrip(
            car: $car,
            distanceMeters: $route->distanceMeters,
            durationMinutes: $route->estimatedTourDurationMinutes,
            passengers: $passengers,
            promoCode: $promoCode,
            customerEmail: $customerEmail,
        );
    }

    /** @param list<RoutePoint> $points */
    private function validatePoints(array $points): void
    {
        if (count($points) < 2) {
            throw new InvalidArgumentException('A route requires at least two points.');
        }

        foreach ($points as $point) {
            if (! $point instanceof RoutePoint) {
                throw new InvalidArgumentException('Every route point must be a RoutePoint instance.');
            }
        }
    }

    private function distanceBetween(RoutePoint $from, RoutePoint $to): float
    {
        $latitudeDelta = deg2rad($to->latitude - $from->latitude);
        $longitudeDelta = deg2rad($to->longitude - $from->longitude);
        $fromLatitude = deg2rad($from->latitude);
        $toLatitude = deg2rad($to->latitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
