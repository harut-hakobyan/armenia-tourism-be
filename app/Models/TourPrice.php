<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TourPrice extends Model
{
    protected $fillable = [
        'tour_id', 'car_category', 'min_passengers', 'max_passengers', 'valid_from',
        'valid_until', 'fixed_price_minor', 'adjustment_minor', 'currency', 'active',
    ];

    protected function casts(): array
    {
        return [
            'car_category' => CarCategory::class,
            'min_passengers' => 'integer',
            'max_passengers' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'fixed_price_minor' => 'integer',
            'adjustment_minor' => 'integer',
            'currency' => CurrencyCode::class,
            'active' => 'boolean',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
