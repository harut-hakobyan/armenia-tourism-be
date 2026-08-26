<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\BookingNotificationService;
use App\Events\BookingCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendBookingCreatedNotifications implements ShouldQueue
{
    public function __construct(private readonly BookingNotificationService $notifications) {}

    public function handle(BookingCreated $event): void
    {
        $this->notifications->sendBookingCreated($event->booking->fresh());
    }
}
