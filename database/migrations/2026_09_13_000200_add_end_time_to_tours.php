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
            $table->time('end_time')->nullable()->after('start_time');
        });

        foreach (DB::table('tours')->whereNotNull('start_time')->get(['id', 'start_time', 'duration_minutes']) as $tour) {
            $startsAt = new DateTimeImmutable((string) $tour->start_time);
            DB::table('tours')->where('id', $tour->id)->update([
                'end_time' => $startsAt
                    ->modify('+'.(int) $tour->duration_minutes.' minutes')
                    ->format('H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn('end_time');
        });
    }
};
