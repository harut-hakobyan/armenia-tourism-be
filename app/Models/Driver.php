<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'preferred_car_id', 'first_name', 'last_name', 'phone',
        'email', 'languages', 'experience_years', 'license_number', 'active', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'experience_years' => 'integer',
            'active' => 'boolean',
            'rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredCar(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'preferred_car_id');
    }

    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'driver_cars')->withTimestamps();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
