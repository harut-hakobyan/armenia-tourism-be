<?php

declare(strict_types=1);

namespace App\Enums;

enum CarCategory: string
{
    case Economy = 'economy';
    case Comfort = 'comfort';
    case Business = 'business';
    case Suv = 'suv';
    case Minivan = 'minivan';
    case Premium = 'premium';
    case Bus = 'bus';
}
