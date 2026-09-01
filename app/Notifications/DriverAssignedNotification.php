<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DriverAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Trip assigned — {$this->booking->booking_number}")
            ->greeting("Hello {$this->booking->driver->first_name},")
            ->line("Pickup: {$this->booking->pickup_address}")
            ->line("Starts: {$this->booking->starts_at->format('d M Y H:i')}")
            ->line("Customer: {$this->booking->customer_name}")
            ->line("Customer phone: {$this->booking->customer_phone}")
            ->line("Car: {$this->booking->car->displayName()}")
            ->line('Open the driver interface to review the trip and update its status.');
    }
}
