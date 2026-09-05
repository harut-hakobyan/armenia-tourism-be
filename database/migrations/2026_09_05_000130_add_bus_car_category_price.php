<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('car_category_prices')->updateOrInsert(
            ['category' => 'bus'],
            [
                'fixed_price_minor' => 0,
                'currency' => 'EUR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('car_category_prices')->where('category', 'bus')->delete();
    }
};
