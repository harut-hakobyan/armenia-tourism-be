<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CurrencyCode;
use App\Enums\PromoCodeType;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;

final class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        PromoCode::query()->updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => PromoCodeType::Percentage,
                // Percentage values use basis points: 1,000 = 10.00%.
                'value' => 1000,
                'currency' => CurrencyCode::Eur,
                'min_order_minor' => 0,
                'max_discount_minor' => 2500,
                'valid_from' => null,
                'valid_until' => null,
                'usage_limit' => null,
                'usage_per_customer' => 1,
                'active' => true,
            ],
        );

        PromoCode::query()->updateOrCreate(
            ['code' => 'ARMENIA25'],
            [
                'type' => PromoCodeType::Fixed,
                'value' => 2500,
                'currency' => CurrencyCode::Eur,
                'min_order_minor' => 20000,
                'max_discount_minor' => 2500,
                'valid_from' => null,
                'valid_until' => null,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'active' => true,
            ],
        );
    }
}
