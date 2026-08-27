<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Booking;

interface BookingNotificationService
{
    public function sendBookingCreated(Booking $booking): void;

    public function sendDriverAssigned(Booking $booking): void;
}
