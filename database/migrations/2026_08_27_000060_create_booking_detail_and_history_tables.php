<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->char('request_fingerprint', 64)->after('idempotency_key');
        });

        Schema::create('booking_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_idempotency_keys', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->char('request_fingerprint', 64);
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tour_booking_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->json('tour_snapshot');
            $table->timestamps();
        });

        Schema::create('transfer_booking_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('flight_number', 50)->nullable();
            $table->dateTime('arrival_at')->nullable();
            $table->boolean('airport_pickup_sign')->default(false);
            $table->string('pickup_sign_name')->nullable();
            $table->boolean('child_seat')->default(false);
            $table->unsignedSmallInteger('extra_waiting_minutes')->default(0);
            $table->unsignedInteger('estimated_distance_meters');
            $table->unsignedInteger('estimated_duration_minutes');
            $table->json('route_snapshot');
            $table->timestamps();
        });

        Schema::create('private_driver_booking_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('package_code', 50);
            $table->json('desired_destinations')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_trip_booking_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('return_to_yerevan')->default(false);
            $table->unsignedInteger('estimated_distance_meters');
            $table->unsignedInteger('estimated_driving_minutes');
            $table->unsignedInteger('estimated_tour_minutes');
            $table->string('route_provider', 50);
            $table->json('route_snapshot');
            $table->timestamps();
        });

        Schema::create('custom_trip_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_trip_booking_detail_id');
            $table->foreign('custom_trip_booking_detail_id', 'custom_trip_detail_id_fk')
                ->references('id')
                ->on('custom_trip_booking_details')
                ->cascadeOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('stop_order');
            $table->string('label')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
            $table->unique(['custom_trip_booking_detail_id', 'stop_order'], 'custom_trip_stop_order_unique');
        });

        Schema::create('booking_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('custom_trip_stops');
        Schema::dropIfExists('custom_trip_booking_details');
        Schema::dropIfExists('private_driver_booking_details');
        Schema::dropIfExists('transfer_booking_details');
        Schema::dropIfExists('tour_booking_details');
        Schema::dropIfExists('booking_idempotency_keys');
        Schema::dropIfExists('booking_sequences');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('request_fingerprint');
        });
    }
};
