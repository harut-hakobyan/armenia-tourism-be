<?php

declare(strict_types=1);

namespace App\Enums;

enum CarType: string
{
    case Sedan = 'sedan';
    case Minivan = 'minivan';
    case Minibus = 'minibus';
    case Bus = 'bus';

    public function passengerCapacity(): int
    {
        return match ($this) {
            self::Sedan => 4,
            self::Minivan => 6,
            self::Minibus => 10,
            self::Bus => 20,
        };
    }
}
