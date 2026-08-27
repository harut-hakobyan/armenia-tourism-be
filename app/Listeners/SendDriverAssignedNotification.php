<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\BookingNotificationService;
use App\Events\DriverAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendDriverAssignedNotification implements ShouldQueue
{
    public function __construct(private readonly BookingNotificationService $notifications) {}

    public function handle(DriverAssigned $event): void
    {
        $this->notifications->sendDriverAssigned($event->booking->fresh(['driver', 'car']));
    }
}
