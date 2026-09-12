<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CarCategory;
use App\Enums\CarType;
use App\Enums\CurrencyCode;
use App\Models\Car;
use App\Models\CarTypePrice;
use Illuminate\Database\Seeder;

final class CarSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [
            ['Toyota', 'Corolla', 2022, 'AMT-101', 'White', CarCategory::Economy, CarType::Sedan, 4, 2],
            ['Toyota', 'Camry', 2023, 'AMT-201', 'Black', CarCategory::Comfort, CarType::Sedan, 4, 2],
            ['Mercedes-Benz', 'E-Class', 2022, 'AMT-301', 'Black', CarCategory::Business, CarType::Sedan, 4, 2],
            ['Toyota', 'Land Cruiser Prado', 2021, 'AMT-401', 'Silver', CarCategory::Suv, CarType::Sedan, 4, 4],
            ['Mercedes-Benz', 'Vito', 2022, 'AMT-501', 'Black', CarCategory::Minivan, CarType::Minivan, 6, 7],
            ['Mercedes-Benz', 'S-Class', 2023, 'AMT-601', 'Black', CarCategory::Premium, CarType::Premier, 3, 2],
            ['Mercedes-Benz', 'Sprinter', 2023, 'AMT-701', 'Black', CarCategory::Comfort, CarType::Minibus, 10, 10],
            ['Mercedes-Benz', 'Tourismo', 2023, 'AMT-801', 'White', CarCategory::Comfort, CarType::Bus, 20, 20],
        ];

        $typePrices = [
            CarType::Premier->value => [7000, 70],
            CarType::Sedan->value => [7000, 70],
            CarType::Minivan->value => [14000, 140],
            CarType::Minibus->value => [18000, 180],
            CarType::Bus->value => [25000, 250],
        ];

        foreach ($typePrices as $type => [$fixedPrice, $pricePerKilometre]) {
            CarTypePrice::query()->updateOrCreate(
                ['type' => $type],
                [
                    'fixed_price_minor' => $fixedPrice,
                    'price_per_km_minor' => $pricePerKilometre,
                    'currency' => CurrencyCode::Eur,
                ],
            );
        }

        foreach ($cars as [$brand, $model, $year, $plate, $color, $category, $type, $passengers, $luggage]) {
            [$base, $pricePerKilometre] = $typePrices[$type->value];
            Car::query()->updateOrCreate(
                ['plate_number' => $plate],
                [
                    'brand' => $brand,
                    'model' => $model,
                    'year' => $year,
                    'color' => $color,
                    'category' => $category,
                    'type' => $type,
                    'passenger_capacity' => $type->fixedPassengerCapacity() ?? $passengers,
                    'luggage_capacity' => $luggage,
                    'transmission' => 'automatic',
                    'air_conditioning' => true,
                    'wifi' => $category !== CarCategory::Economy,
                    'child_seat_available' => true,
                    'base_price_minor' => $base,
                    'price_per_km_minor' => $pricePerKilometre,
                    'price_per_hour_minor' => 0,
                    'currency' => CurrencyCode::Eur,
                    'active' => true,
                    'available_for_booking' => true,
                ],
            );
        }
    }
}
