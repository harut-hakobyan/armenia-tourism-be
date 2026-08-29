<?php

declare(strict_types=1);

namespace App\Enums;

enum GroupTourDepartureStatus: string
{
    case Scheduled = 'scheduled';
    case Full = 'full';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
