<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tour_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['tour_category_id', 'locale'], 'tour_category_locale_unique');
        });

        Schema::create('tours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('tour_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('approximate_distance_km')->nullable();
            $table->unsignedBigInteger('starting_price_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('pricing_type', 32)->default('per_car')->index();
            $table->boolean('active')->default(true)->index();
            $table->boolean('featured')->default(false)->index();
            $table->unsignedTinyInteger('max_passengers')->nullable();
            $table->boolean('pickup_available')->default(true);
            $table->boolean('dropoff_available')->default(true);
            $table->unsignedSmallInteger('free_cancellation_hours')->default(24);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tour_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['tour_id', 'locale']);
            $table->index(['locale', 'title']);
        });

        Schema::create('tour_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('overnight_location')->nullable();
            $table->timestamps();
            $table->unique(['tour_id', 'day_number']);
        });

        Schema::create('tour_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_day_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('day_number')->default(1);
            $table->unsignedSmallInteger('stop_order');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('optional')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tour_id', 'day_number', 'stop_order']);
            $table->index(['tour_day_id', 'stop_order']);
        });

        Schema::create('tour_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('car_category', 32)->nullable()->index();
            $table->unsignedTinyInteger('min_passengers')->nullable();
            $table->unsignedTinyInteger('max_passengers')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('fixed_price_minor')->nullable();
            $table->bigInteger('adjustment_minor')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['tour_id', 'valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_prices');
        Schema::dropIfExists('tour_stops');
        Schema::dropIfExists('tour_days');
        Schema::dropIfExists('tour_translations');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('tour_category_translations');
        Schema::dropIfExists('tour_categories');
    }
};
