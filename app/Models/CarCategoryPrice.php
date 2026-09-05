<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;

final class CarCategoryPrice extends Model
{
    protected $fillable = ['category', 'fixed_price_minor', 'currency'];

    protected function casts(): array
    {
        return [
            'category' => CarCategory::class,
            'fixed_price_minor' => 'integer',
            'currency' => CurrencyCode::class,
        ];
    }
}
