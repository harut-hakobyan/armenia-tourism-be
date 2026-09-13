<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_translations', function (Blueprint $table): void {
            $table->json('inclusions')->nullable()->after('description');
            $table->json('exclusions')->nullable()->after('inclusions');
        });
    }

    public function down(): void
    {
        Schema::table('tour_translations', function (Blueprint $table): void {
            $table->dropColumn(['inclusions', 'exclusions']);
        });
    }
};
