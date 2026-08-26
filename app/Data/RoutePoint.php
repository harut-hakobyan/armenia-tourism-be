<?php

declare(strict_types=1);

namespace App\Data;

use InvalidArgumentException;

final readonly class RoutePoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $label = null,
    ) {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Route coordinates are outside valid bounds.');
        }
    }
}
