<?php

declare(strict_types=1);

namespace App\Enums;

enum DriverTripStatus: string
{
    case Assigned = 'assigned';
    case OnTheWay = 'on_the_way';
    case Arrived = 'arrived';
    case PassengerPickedUp = 'passenger_picked_up';
    case TripStarted = 'trip_started';
    case Completed = 'completed';
}
