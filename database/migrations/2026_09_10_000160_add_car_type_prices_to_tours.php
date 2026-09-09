<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, int> */
    private const CAPACITIES = [
        'coupe' => 3,
        'sedan' => 4,
        'minivan' => 6,
        'minibus' => 10,
        'bus' => 20,
    ];

    public function up(): void
    {
        Schema::table('tour_prices', function (Blueprint $table): void {
            $table->string('car_type', 32)->nullable()->after('car_category');
            $table->unique(['tour_id', 'car_type'], 'tour_prices_tour_car_type_unique');
        });

        $globalPrices = DB::table('car_type_prices')->pluck('fixed_price_minor', 'type');
        $sedanPrice = (int) ($globalPrices['sedan'] ?? 0);

        DB::table('tours')->where('format', 'private')->orderBy('id')->each(function (object $tour) use ($globalPrices, $sedanPrice): void {
            foreach (self::CAPACITIES as $type => $capacity) {
                $adjustment = (int) ($globalPrices[$type] ?? $sedanPrice) - $sedanPrice;
                DB::table('tour_prices')->updateOrInsert(
                    ['tour_id' => $tour->id, 'car_type' => $type],
                    [
                        'car_category' => null,
                        'min_passengers' => 1,
                        'max_passengers' => $capacity,
                        'valid_from' => null,
                        'valid_until' => null,
                        'fixed_price_minor' => max(0, (int) $tour->starting_price_minor + $adjustment),
                        'adjustment_minor' => 0,
                        'currency' => $tour->currency,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('tour_prices')->whereNotNull('car_type')->delete();

        Schema::table('tour_prices', function (Blueprint $table): void {
            $table->dropUnique('tour_prices_tour_car_type_unique');
            $table->dropColumn('car_type');
        });
    }
};
