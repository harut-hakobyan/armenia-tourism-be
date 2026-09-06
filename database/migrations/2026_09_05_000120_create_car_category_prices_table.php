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
        Schema::create('car_category_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 32)->unique();
            $table->unsignedBigInteger('fixed_price_minor')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });

        foreach (['economy', 'comfort', 'business', 'suv', 'minivan', 'premium', 'bus'] as $category) {
            $car = DB::table('cars')->where('category', $category)->whereNull('deleted_at')->orderBy('id')->first();
            DB::table('car_category_prices')->insert([
                'category' => $category,
                'fixed_price_minor' => (int) ($car->base_price_minor ?? 0),
                'currency' => (string) ($car->currency ?? 'EUR'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('cars')->where('category', $category)->update([
                'base_price_minor' => (int) ($car->base_price_minor ?? 0),
                'price_per_km_minor' => 0,
                'price_per_hour_minor' => 0,
                'currency' => (string) ($car->currency ?? 'EUR'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('car_category_prices');
    }
};
