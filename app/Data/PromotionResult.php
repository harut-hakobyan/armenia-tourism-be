<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PromotionResult
{
    public function __construct(
        public int $promoCodeId,
        public string $code,
        public int $discountMinor,
    ) {}
}
