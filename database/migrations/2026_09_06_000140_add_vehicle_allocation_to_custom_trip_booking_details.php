<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_trip_booking_details', function (Blueprint $table): void {
            $table->string('vehicle_category', 32)->nullable()->after('return_to_yerevan');
            $table->unsignedSmallInteger('vehicle_count')->default(1)->after('vehicle_category');
            $table->unsignedSmallInteger('vehicle_capacity')->nullable()->after('vehicle_count');
        });
    }

    public function down(): void
    {
        Schema::table('custom_trip_booking_details', function (Blueprint $table): void {
            $table->dropColumn(['vehicle_category', 'vehicle_count', 'vehicle_capacity']);
        });
    }
};
