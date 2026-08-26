<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomTripBookingDetail extends Model
{
    protected $fillable = [
        'booking_id', 'return_to_yerevan', 'estimated_distance_meters',
        'estimated_driving_minutes', 'estimated_tour_minutes', 'route_provider', 'route_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'return_to_yerevan' => 'boolean',
            'estimated_distance_meters' => 'integer',
            'estimated_driving_minutes' => 'integer',
            'estimated_tour_minutes' => 'integer',
            'route_snapshot' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(CustomTripStop::class)->orderBy('stop_order');
    }
}
