<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Tour extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'slug', 'duration_minutes', 'approximate_distance_km',
        'starting_price_minor', 'currency', 'pricing_type', 'active', 'featured',
        'max_passengers', 'pickup_available', 'dropoff_available',
        'free_cancellation_hours', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'approximate_distance_km' => 'integer',
            'starting_price_minor' => 'integer',
            'currency' => CurrencyCode::class,
            'pricing_type' => PricingType::class,
            'active' => 'boolean',
            'featured' => 'boolean',
            'max_passengers' => 'integer',
            'pickup_available' => 'boolean',
            'dropoff_available' => 'boolean',
            'free_cancellation_hours' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'category_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TourTranslation::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class)->orderBy('day_number');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TourStop::class)->orderBy('day_number')->orderBy('stop_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TourPrice::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
