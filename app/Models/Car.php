<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Car extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand', 'model', 'year', 'plate_number', 'color', 'category',
        'passenger_capacity', 'luggage_capacity', 'transmission',
        'air_conditioning', 'wifi', 'child_seat_available',
        'base_price_minor', 'price_per_km_minor', 'price_per_hour_minor',
        'currency', 'active', 'available_for_booking',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'category' => CarCategory::class,
            'passenger_capacity' => 'integer',
            'luggage_capacity' => 'integer',
            'air_conditioning' => 'boolean',
            'wifi' => 'boolean',
            'child_seat_available' => 'boolean',
            'base_price_minor' => 'integer',
            'price_per_km_minor' => 'integer',
            'price_per_hour_minor' => 'integer',
            'currency' => CurrencyCode::class,
            'active' => 'boolean',
            'available_for_booking' => 'boolean',
        ];
    }

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_cars')->withTimestamps();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function groupTourDepartures(): HasMany
    {
        return $this->hasMany(GroupTourDeparture::class);
    }
}
