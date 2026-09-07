<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CarType;
use App\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;

final class CarTypePrice extends Model
{
    protected $fillable = ['type', 'fixed_price_minor', 'price_per_km_minor', 'currency'];

    protected function casts(): array
    {
        return [
            'type' => CarType::class,
            'fixed_price_minor' => 'integer',
            'price_per_km_minor' => 'integer',
            'currency' => CurrencyCode::class,
        ];
    }
}
