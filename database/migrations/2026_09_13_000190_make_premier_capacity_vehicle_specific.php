<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tour_prices')
            ->where('car_type', 'premier')
            ->update(['max_passengers' => null]);
    }

    public function down(): void
    {
        DB::table('tour_prices')
            ->where('car_type', 'premier')
            ->update(['max_passengers' => 3]);
    }
};
