<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookingCheckIn extends Model
{
    protected $fillable = [
        'booking_id', 'checked_in_by_user_id', 'passengers_checked_in', 'total_checked_in',
        'checked_in_at', 'method', 'notes', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'passengers_checked_in' => 'integer',
            'total_checked_in' => 'integer',
            'checked_in_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }
}
