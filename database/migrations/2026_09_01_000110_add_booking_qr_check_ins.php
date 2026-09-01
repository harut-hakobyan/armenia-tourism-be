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
            $table->char('check_in_token_hash', 64)->nullable()->unique()->after('secure_token_hash');
            $table->string('attendance_status', 32)->default('expected')->index()->after('booking_status');
            $table->unsignedTinyInteger('checked_in_passengers')->default(0)->after('passengers');
            $table->timestamp('last_checked_in_at')->nullable()->after('checked_in_passengers');
        });

        $key = (string) config('app.key');
        DB::table('bookings')->select(['id', 'uuid'])->orderBy('id')->chunkById(500, function ($bookings) use ($key): void {
            foreach ($bookings as $booking) {
                $binary = hash_hmac('sha256', 'booking-check-in:'.$booking->uuid, $key, true);
                $token = rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
                DB::table('bookings')->where('id', $booking->id)->update([
                    'check_in_token_hash' => hash('sha256', $token),
                ]);
            }
        });

        Schema::create('booking_check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('passengers_checked_in');
            $table->unsignedTinyInteger('total_checked_in');
            $table->timestamp('checked_in_at')->index();
            $table->string('method', 32)->default('qr');
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_check_ins');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['check_in_token_hash']);
            $table->dropIndex(['attendance_status']);
            $table->dropColumn([
                'check_in_token_hash', 'attendance_status', 'checked_in_passengers', 'last_checked_in_at',
            ]);
        });
    }
};
