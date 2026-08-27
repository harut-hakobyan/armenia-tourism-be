<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverTripStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DriverTripStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'driver_trip_status_history';

    protected $fillable = [
        'booking_id', 'driver_id', 'user_id', 'from_status', 'to_status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DriverTripStatus::class,
            'to_status' => DriverTripStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
