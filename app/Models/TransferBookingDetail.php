<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferBookingDetail extends Model
{
    protected $fillable = [
        'booking_id', 'flight_number', 'arrival_at', 'airport_pickup_sign',
        'pickup_sign_name', 'child_seat', 'extra_waiting_minutes',
        'estimated_distance_meters', 'estimated_duration_minutes', 'route_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'arrival_at' => 'immutable_datetime',
            'airport_pickup_sign' => 'boolean',
            'child_seat' => 'boolean',
            'extra_waiting_minutes' => 'integer',
            'estimated_distance_meters' => 'integer',
            'estimated_duration_minutes' => 'integer',
            'route_snapshot' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
