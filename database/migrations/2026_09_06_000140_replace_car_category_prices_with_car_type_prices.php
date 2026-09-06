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
        $categoryPrices = DB::table('car_category_prices')->get()->keyBy('category');

        Schema::table('cars', function (Blueprint $table): void {
            $table->string('type', 32)->default('sedan')->index()->after('category');
        });

        DB::table('cars')->where('passenger_capacity', '>', 4)->update(['type' => 'minivan']);
        DB::table('cars')->where('passenger_capacity', '>', 6)->update(['type' => 'minibus']);
        DB::table('cars')->where('passenger_capacity', '>', 10)->update(['type' => 'bus']);
        DB::table('cars')->where('category', 'minivan')->update(['type' => 'minivan']);
        DB::table('cars')->where('category', 'bus')->update(['type' => 'bus']);

        foreach (['sedan' => 4, 'minivan' => 6, 'minibus' => 10, 'bus' => 20] as $type => $capacity) {
            DB::table('cars')->where('type', $type)->update(['passenger_capacity' => $capacity]);
        }

        Schema::create('car_type_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32)->unique();
            $table->unsignedBigInteger('fixed_price_minor')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });

        $sourceCategories = [
            'sedan' => ['economy', 'comfort', 'business', 'suv', 'premium'],
            'minivan' => ['minivan'],
            'minibus' => ['minivan', 'bus'],
            'bus' => ['bus', 'minivan'],
        ];

        foreach ($sourceCategories as $type => $categories) {
            $candidates = collect($categories)
                ->map(fn (string $category) => $categoryPrices->get($category));
            $source = $candidates->first(
                fn ($price) => $price !== null && (int) $price->fixed_price_minor > 0,
            ) ?? $candidates->first(fn ($price) => $price !== null);

            DB::table('car_type_prices')->insert([
                'type' => $type,
                'fixed_price_minor' => (int) ($source->fixed_price_minor ?? 0),
                'currency' => (string) ($source->currency ?? 'EUR'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cars')->where('type', $type)->update([
                'base_price_minor' => (int) ($source->fixed_price_minor ?? 0),
                'price_per_km_minor' => 0,
                'price_per_hour_minor' => 0,
                'currency' => (string) ($source->currency ?? 'EUR'),
            ]);
        }

        Schema::dropIfExists('car_category_prices');
    }

    public function down(): void
    {
        $typePrices = DB::table('car_type_prices')->get()->keyBy('type');

        Schema::create('car_category_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 32)->unique();
            $table->unsignedBigInteger('fixed_price_minor')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });

        $categoryTypes = [
            'economy' => 'sedan',
            'comfort' => 'sedan',
            'business' => 'sedan',
            'suv' => 'sedan',
            'minivan' => 'minivan',
            'premium' => 'sedan',
            'bus' => 'bus',
        ];

        foreach ($categoryTypes as $category => $type) {
            $source = $typePrices->get($type);
            DB::table('car_category_prices')->insert([
                'category' => $category,
                'fixed_price_minor' => (int) ($source->fixed_price_minor ?? 0),
                'currency' => (string) ($source->currency ?? 'EUR'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('car_type_prices');
        Schema::table('cars', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
