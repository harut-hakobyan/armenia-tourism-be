<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomTripStop extends Model
{
    protected $fillable = [
        'custom_trip_booking_detail_id', 'destination_id', 'stop_order',
        'label', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'stop_order' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(CustomTripBookingDetail::class, 'custom_trip_booking_detail_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
