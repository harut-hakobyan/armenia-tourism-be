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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('requested_car_type', 32)->nullable()->after('car_id')->index();
        });

        DB::table('bookings')
            ->join('cars', 'cars.id', '=', 'bookings.car_id')
            ->whereNull('bookings.requested_car_type')
            ->select(['bookings.id', 'cars.type'])
            ->orderBy('bookings.id')
            ->each(fn (object $booking) => DB::table('bookings')->where('id', $booking->id)->update([
                'requested_car_type' => $booking->type,
            ]));
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['requested_car_type']);
            $table->dropColumn('requested_car_type');
        });
    }
};
