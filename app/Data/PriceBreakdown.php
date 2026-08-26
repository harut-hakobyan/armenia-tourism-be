<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\CurrencyCode;
use InvalidArgumentException;

final readonly class PriceBreakdown
{
    /** @param array<string, int> $adjustments */
    public function __construct(
        public int $baseMinor,
        public array $adjustments,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $totalMinor,
        public CurrencyCode $currency,
        public ?string $promoCode = null,
    ) {
        if ($baseMinor < 0 || $subtotalMinor < 0 || $discountMinor < 0 || $totalMinor < 0) {
            throw new InvalidArgumentException('Money amounts cannot be negative.');
        }

        if ($totalMinor !== max(0, $subtotalMinor - $discountMinor)) {
            throw new InvalidArgumentException('Price breakdown totals are inconsistent.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'base_minor' => $this->baseMinor,
            'adjustments' => $this->adjustments,
            'subtotal_minor' => $this->subtotalMinor,
            'discount_minor' => $this->discountMinor,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency->value,
            'promo_code' => $this->promoCode,
        ];
    }
}
