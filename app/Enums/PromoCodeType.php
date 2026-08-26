<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoCodeType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
