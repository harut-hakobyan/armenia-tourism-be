<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminNewBookingNotification extends Notification
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
            ->subject("New booking {$this->booking->booking_number}")
            ->greeting('New Armenia Tourism booking')
            ->line("Service: {$this->booking->service_type->value}")
            ->line("Customer: {$this->booking->customer_name}")
            ->line("Pickup: {$this->booking->pickup_address}")
            ->line("Starts: {$this->booking->starts_at->format('d M Y H:i')}")
            ->line("Total: {$this->booking->total_minor} {$this->booking->currency->value} minor units")
            ->line('Open the admin dashboard to confirm and assign the trip.');
    }
}
