<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyCode: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Amd = 'AMD';
}
