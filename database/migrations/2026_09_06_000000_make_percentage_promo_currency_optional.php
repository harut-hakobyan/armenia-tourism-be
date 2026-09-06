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
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('promo_codes')->whereNull('currency')->update(['currency' => 'EUR']);

        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable(false)->default('EUR')->change();
        });
    }
};
