<?php

declare(strict_types=1);

namespace App\Data;

use InvalidArgumentException;

final readonly class RouteResult
{
    /** @param list<RoutePoint> $points */
    public function __construct(
        public int $distanceMeters,
        public int $drivingDurationMinutes,
        public int $estimatedTourDurationMinutes,
        public array $points,
        public string $provider,
    ) {
        if ($distanceMeters < 0 || $drivingDurationMinutes < 0 || $estimatedTourDurationMinutes < 0) {
            throw new InvalidArgumentException('Route measurements cannot be negative.');
        }
    }
}
