<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('whatsapp', 32)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('locale', 10)->default('en');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 20);
            $table->unsignedBigInteger('value');
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->unsignedBigInteger('max_discount_minor')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_customer')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('booking_number', 32)->unique();
            $table->char('secure_token_hash', 64)->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type', 32)->index();
            $table->date('booking_date')->index();
            $table->time('pickup_time');
            $table->dateTime('starts_at')->index();
            $table->dateTime('planned_end_at')->index();
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->string('dropoff_address')->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->unsignedTinyInteger('passengers');
            $table->string('customer_name');
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_phone', 32);
            $table->string('customer_whatsapp', 32)->nullable();
            $table->string('customer_nationality', 100)->nullable();
            $table->text('customer_notes')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('deposit_amount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3);
            $table->string('payment_method', 32)->default('pay_driver');
            $table->string('payment_status', 32)->default('unpaid')->index();
            $table->string('booking_status', 32)->default('pending')->index();
            $table->json('price_breakdown')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['car_id', 'starts_at', 'planned_end_at'], 'bookings_car_window_index');
            $table->index(['driver_id', 'starts_at', 'planned_end_at'], 'bookings_driver_window_index');
            $table->index(['booking_date', 'booking_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('customers');
    }
};
