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
        Schema::table('tours', function (Blueprint $table): void {
            $table->time('group_start_time')->nullable()->after('format');
            $table->string('group_meeting_point')->nullable()->after('group_start_time');
        });

        DB::table('tours')->where('format', 'group')->update([
            'group_start_time' => '09:00:00',
            'group_meeting_point' => 'Republic Square, Yerevan',
        ]);
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn(['group_start_time', 'group_meeting_point']);
        });
    }
};
