<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Expected = 'expected';
    case PartiallyCheckedIn = 'partially_checked_in';
    case CheckedIn = 'checked_in';
}
