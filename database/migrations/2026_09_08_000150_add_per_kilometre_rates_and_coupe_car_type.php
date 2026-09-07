<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_type_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_per_km_minor')->default(0)->after('fixed_price_minor');
        });

        $typePrices = DB::table('car_type_prices')->get();

        foreach ($typePrices as $price) {
            // Keep the old fixed price equivalent at 100 km as a safe initial rate.
            $perKilometre = (int) round(((int) $price->fixed_price_minor) / 100);
            DB::table('car_type_prices')->where('id', $price->id)->update([
                'price_per_km_minor' => $perKilometre,
            ]);
        }

        $sedanPrice = DB::table('car_type_prices')->where('type', 'sedan')->first();
        DB::table('car_type_prices')->updateOrInsert(
            ['type' => 'coupe'],
            [
                'fixed_price_minor' => (int) ($sedanPrice?->fixed_price_minor ?? 0),
                'price_per_km_minor' => (int) round(((int) ($sedanPrice?->fixed_price_minor ?? 0)) / 100),
                'currency' => (string) ($sedanPrice?->currency ?? 'EUR'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach (DB::table('car_type_prices')->get() as $price) {
            DB::table('cars')->where('type', $price->type)->update([
                'base_price_minor' => (int) $price->fixed_price_minor,
                'price_per_km_minor' => (int) $price->price_per_km_minor,
                'currency' => (string) $price->currency,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cars')->where('type', 'coupe')->update([
            'type' => 'sedan',
            'passenger_capacity' => 4,
        ]);
        DB::table('car_type_prices')->where('type', 'coupe')->delete();

        Schema::table('car_type_prices', function (Blueprint $table): void {
            $table->dropColumn('price_per_km_minor');
        });
    }
};
