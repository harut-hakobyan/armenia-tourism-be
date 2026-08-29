<?php

declare(strict_types=1);

namespace App\Enums;

enum TourFormat: string
{
    case Private = 'private';
    case Group = 'group';
}
