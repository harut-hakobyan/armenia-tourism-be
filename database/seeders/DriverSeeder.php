<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Car;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = [
            ['Arman', 'Petrosyan', '+37499111001', 'arman.driver@armeniatourism.local', 'DRV-AM-1001', ['hy', 'en', 'ru'], 12, 'AMT-201'],
            ['Gor', 'Harutyunyan', '+37499111002', 'gor.driver@armeniatourism.local', 'DRV-AM-1002', ['hy', 'ru'], 9, 'AMT-501'],
        ];

        foreach ($drivers as [$firstName, $lastName, $phone, $email, $license, $languages, $experience, $plate]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$firstName} {$lastName}",
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'password' => env('DRIVER_PASSWORD', 'ChangeMe123!'),
                    'role' => UserRole::Driver,
                    'locale' => 'en',
                    'is_active' => true,
                ],
            );

            $car = Car::query()->where('plate_number', $plate)->firstOrFail();
            $driver = Driver::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'preferred_car_id' => $car->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'languages' => $languages,
                    'experience_years' => $experience,
                    'license_number' => $license,
                    'active' => true,
                    'rating' => 4.90,
                ],
            );

            $driver->cars()->syncWithoutDetaching([$car->id]);
        }
    }
}
