<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@armeniatourism.local')],
            [
                'name' => 'Local Administrator',
                'first_name' => 'Local',
                'last_name' => 'Administrator',
                'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
                'role' => UserRole::Admin,
                'locale' => 'en',
                'is_active' => true,
            ],
        );

        $this->call([
            DestinationSeeder::class,
            TourCategorySeeder::class,
            CarSeeder::class,
            DriverSeeder::class,
            TourSeeder::class,
            PromoCodeSeeder::class,
            CmsSeeder::class,
        ]);
    }
}
