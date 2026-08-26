<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Enums\PromoCodeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PromoCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'type', 'value', 'currency', 'min_order_minor',
        'max_discount_minor', 'valid_from', 'valid_until', 'usage_limit',
        'usage_per_customer', 'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'integer',
            'currency' => CurrencyCode::class,
            'min_order_minor' => 'integer',
            'max_discount_minor' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'usage_limit' => 'integer',
            'usage_per_customer' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
