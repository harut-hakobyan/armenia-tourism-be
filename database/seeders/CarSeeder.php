<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Models\Car;
use Illuminate\Database\Seeder;

final class CarSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [
            ['Toyota', 'Corolla', 2022, 'AMT-101', 'White', CarCategory::Economy, 4, 2, 5000, 45, 1200],
            ['Toyota', 'Camry', 2023, 'AMT-201', 'Black', CarCategory::Comfort, 4, 2, 7000, 55, 1600],
            ['Mercedes-Benz', 'E-Class', 2022, 'AMT-301', 'Black', CarCategory::Business, 4, 2, 11000, 75, 2500],
            ['Toyota', 'Land Cruiser Prado', 2021, 'AMT-401', 'Silver', CarCategory::Suv, 4, 4, 12000, 85, 2800],
            ['Mercedes-Benz', 'Vito', 2022, 'AMT-501', 'Black', CarCategory::Minivan, 7, 7, 14000, 95, 3200],
            ['Mercedes-Benz', 'S-Class', 2023, 'AMT-601', 'Black', CarCategory::Premium, 3, 2, 18000, 120, 4000],
        ];

        foreach ($cars as [$brand, $model, $year, $plate, $color, $category, $passengers, $luggage, $base, $perKm, $perHour]) {
            Car::query()->updateOrCreate(
                ['plate_number' => $plate],
                [
                    'brand' => $brand,
                    'model' => $model,
                    'year' => $year,
                    'color' => $color,
                    'category' => $category,
                    'passenger_capacity' => $passengers,
                    'luggage_capacity' => $luggage,
                    'transmission' => 'automatic',
                    'air_conditioning' => true,
                    'wifi' => $category !== CarCategory::Economy,
                    'child_seat_available' => true,
                    'base_price_minor' => $base,
                    'price_per_km_minor' => $perKm,
                    'price_per_hour_minor' => $perHour,
                    'currency' => CurrencyCode::Eur,
                    'active' => true,
                    'available_for_booking' => true,
                ],
            );
        }
    }
}
