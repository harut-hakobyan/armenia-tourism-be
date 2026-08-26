<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceType: string
{
    case Tour = 'tour';
    case AirportTransfer = 'airport_transfer';
    case PrivateDriver = 'private_driver';
    case CustomTrip = 'custom_trip';
}
