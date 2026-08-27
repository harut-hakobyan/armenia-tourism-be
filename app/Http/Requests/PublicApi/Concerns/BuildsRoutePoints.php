<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi\Concerns;

use App\Data\RoutePoint;

trait BuildsRoutePoints
{
    /** @return array<string, mixed> */
    protected function routePointRules(): array
    {
        return [
            'route_points' => ['required', 'array', 'min:2', 'max:20'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<RoutePoint> */
    public function routePoints(): array
    {
        return array_map(
            static fn (array $point): RoutePoint => new RoutePoint(
                (float) $point['latitude'],
                (float) $point['longitude'],
                $point['label'] ?? null,
            ),
            $this->validated('route_points'),
        );
    }
}
