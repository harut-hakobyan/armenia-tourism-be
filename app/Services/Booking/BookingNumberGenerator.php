<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;

final class BookingNumberGenerator
{
    public function next(int $year): string
    {
        DB::table('booking_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('booking_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = ((int) $sequence->last_number) + 1;

        DB::table('booking_sequences')->where('year', $year)->update([
            'last_number' => $next,
            'updated_at' => now(),
        ]);

        return sprintf('AMT-%d-%06d', $year, $next);
    }
}
