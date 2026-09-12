<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('car_type_prices')->where('type', 'coupe')->update(['type' => 'premier']);
        DB::table('tour_prices')->where('car_type', 'coupe')->update(['car_type' => 'premier']);
        DB::table('bookings')->where('requested_car_type', 'coupe')->update(['requested_car_type' => 'premier']);

        $premierPrice = DB::table('car_type_prices')->where('type', 'premier')->first();
        $carChanges = [
            'type' => 'premier',
        ];
        if ($premierPrice) {
            $carChanges += [
                'base_price_minor' => (int) $premierPrice->fixed_price_minor,
                'price_per_km_minor' => (int) $premierPrice->price_per_km_minor,
                'price_per_hour_minor' => 0,
                'currency' => (string) $premierPrice->currency,
            ];
        }

        DB::table('cars')
            ->where(fn ($query) => $query
                ->where('type', 'coupe')
                ->orWhere('category', 'premium'))
            ->update($carChanges);
    }

    public function down(): void
    {
        $sedanPrice = DB::table('car_type_prices')->where('type', 'sedan')->first();
        $coupePrice = DB::table('car_type_prices')->where('type', 'premier')->first();
        $sedanChanges = [
            'type' => 'sedan',
        ];
        $coupeChanges = [
            'type' => 'coupe',
        ];
        if ($sedanPrice) {
            $sedanChanges += [
                'base_price_minor' => (int) $sedanPrice->fixed_price_minor,
                'price_per_km_minor' => (int) $sedanPrice->price_per_km_minor,
                'price_per_hour_minor' => 0,
                'currency' => (string) $sedanPrice->currency,
            ];
        }
        if ($coupePrice) {
            $coupeChanges += [
                'base_price_minor' => (int) $coupePrice->fixed_price_minor,
                'price_per_km_minor' => (int) $coupePrice->price_per_km_minor,
                'price_per_hour_minor' => 0,
                'currency' => (string) $coupePrice->currency,
            ];
        }

        DB::table('cars')->where('type', 'premier')->where('category', 'premium')->update($sedanChanges);
        DB::table('cars')->where('type', 'premier')->update($coupeChanges);
        DB::table('bookings')->where('requested_car_type', 'premier')->update(['requested_car_type' => 'coupe']);
        DB::table('tour_prices')->where('car_type', 'premier')->update(['car_type' => 'coupe']);
        DB::table('car_type_prices')->where('type', 'premier')->update(['type' => 'coupe']);
    }
};
