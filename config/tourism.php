<?php

declare(strict_types=1);

return [
    'locales' => ['en', 'ru', 'hy'],

    'notifications' => [
        'admin_email' => env('BOOKING_ADMIN_EMAIL', 'admin@armeniatourism.local'),
    ],

    'routing' => [
        // MVP fallback only. Replace the bound contract with Mapbox/Google in production.
        'road_factor' => (float) env('ROUTE_ROAD_FACTOR', 1.20),
        'average_speed_kmh' => (int) env('ROUTE_AVERAGE_SPEED_KMH', 55),
        'default_stop_minutes' => (int) env('ROUTE_DEFAULT_STOP_MINUTES', 45),
    ],
];
