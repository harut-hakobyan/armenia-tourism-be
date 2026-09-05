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
            $table->renameColumn('group_start_time', 'start_time');
            $table->renameColumn('group_meeting_point', 'meeting_point');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->renameColumn('start_time', 'group_start_time');
            $table->renameColumn('meeting_point', 'group_meeting_point');
        });
    }
};
