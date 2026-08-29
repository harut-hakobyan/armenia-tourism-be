<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CurrencyCode;
use App\Enums\GroupTourDepartureStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GroupTourDeparture extends Model
{
    protected $fillable = [
        'tour_id', 'car_id', 'driver_id', 'starts_at', 'ends_at', 'meeting_point',
        'capacity', 'price_per_person_minor', 'currency', 'status', 'active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'price_per_person_minor' => 'integer',
            'currency' => CurrencyCode::class,
            'status' => GroupTourDepartureStatus::class,
            'active' => 'boolean',
        ];
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where('status', GroupTourDepartureStatus::Scheduled)
            ->where('starts_at', '>', now());
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookedSeats(): int
    {
        return (int) $this->bookings()
            ->whereNotIn('booking_status', [
                BookingStatus::Cancelled->value,
                BookingStatus::NoShow->value,
            ])->sum('passengers');
    }

    public function remainingSeats(): int
    {
        return max(0, $this->capacity - $this->bookedSeats());
    }
}
