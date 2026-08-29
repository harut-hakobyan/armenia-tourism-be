<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_chat_id', 32)->nullable()->unique()->after('locale');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->boolean('telegram_notifications_enabled')->default(true)->after('telegram_username');
            $table->string('telegram_link_token_hash', 64)->nullable()->index()->after('telegram_notifications_enabled');
            $table->timestamp('telegram_link_token_expires_at')->nullable()->after('telegram_link_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropIndex(['telegram_link_token_hash']);
            $table->dropColumn(['telegram_chat_id', 'telegram_username', 'telegram_notifications_enabled', 'telegram_link_token_hash', 'telegram_link_token_expires_at']);
        });
    }
};
