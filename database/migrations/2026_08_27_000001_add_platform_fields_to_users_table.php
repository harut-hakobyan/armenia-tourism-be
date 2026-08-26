<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('role', 32)->default(UserRole::Customer->value)->index();
            $table->string('locale', 10)->default('en');
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'role',
                'locale',
                'is_active',
                'deleted_at',
            ]);
        });
    }
};
