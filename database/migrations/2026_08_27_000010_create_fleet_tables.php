<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table): void {
            $table->id();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->unsignedSmallInteger('year');
            $table->string('plate_number', 32)->unique();
            $table->string('color', 50)->nullable();
            $table->string('category', 32)->index();
            $table->unsignedTinyInteger('passenger_capacity');
            $table->unsignedTinyInteger('luggage_capacity')->default(0);
            $table->string('transmission', 20)->nullable();
            $table->boolean('air_conditioning')->default(true);
            $table->boolean('wifi')->default(false);
            $table->boolean('child_seat_available')->default(false);
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->unsignedBigInteger('price_per_km_minor')->default(0);
            $table->unsignedBigInteger('price_per_hour_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->boolean('active')->default(true)->index();
            $table->boolean('available_for_booking')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('preferred_car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32);
            $table->string('email')->nullable()->index();
            $table->json('languages')->nullable();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->string('license_number', 100)->unique();
            $table->boolean('active')->default(true)->index();
            $table->decimal('rating', 3, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('driver_cars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['driver_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_cars');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('cars');
    }
};
