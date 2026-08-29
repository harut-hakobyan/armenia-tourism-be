<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->string('format', 20)->default('private')->after('pricing_type')->index();
        });

        Schema::create('group_tour_departures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('meeting_point');
            $table->unsignedTinyInteger('capacity');
            $table->unsignedBigInteger('price_per_person_minor')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 20)->default('scheduled')->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tour_id', 'starts_at']);
            $table->index(['tour_id', 'active', 'starts_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('group_tour_departure_id')->nullable()->after('tour_id')
                ->constrained()->nullOnDelete();
            $table->index(['group_tour_departure_id', 'booking_status'], 'bookings_group_departure_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_tour_departure_id');
        });
        Schema::dropIfExists('group_tour_departures');
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn('format');
        });
    }
};
