<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyCode: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Amd = 'AMD';

    public function formatMinor(int $amount): string
    {
        return match ($this) {
            self::Amd => number_format($amount, 0, '.', ','),
            self::Eur, self::Usd => number_format($amount / 100, 2, '.', ','),
        };
    }
}
