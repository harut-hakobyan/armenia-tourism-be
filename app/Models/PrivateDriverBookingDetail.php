<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PrivateDriverBookingDetail extends Model
{
    protected $fillable = [
        'booking_id', 'duration_minutes', 'package_code', 'desired_destinations',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'desired_destinations' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
