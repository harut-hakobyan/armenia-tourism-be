<?php

declare(strict_types=1);

namespace App\Enums;

enum PricingType: string
{
    case PerCar = 'per_car';
    case PerPerson = 'per_person';
    case Fixed = 'fixed';
    case Custom = 'custom';
}
