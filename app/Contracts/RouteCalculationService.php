<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\PriceBreakdown;
use App\Data\RoutePoint;
use App\Data\RouteResult;
use App\Models\Car;

interface RouteCalculationService
{
    /** @param list<RoutePoint> $points */
    public function calculateDistance(array $points): int;

    /** @param list<RoutePoint> $points */
    public function calculateDuration(array $points): int;

    /** @param list<RoutePoint> $points */
    public function calculateRoute(array $points): RouteResult;

    public function calculateEstimatedPrice(
        RouteResult $route,
        Car $car,
        int $passengers = 1,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): PriceBreakdown;
}
