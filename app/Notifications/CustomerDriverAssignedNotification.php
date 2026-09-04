<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerDriverAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $publicUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $driver = $this->booking->driver;

        return (new MailMessage)
            ->subject("Driver assigned - {$this->booking->booking_number}")
            ->greeting("Hello {$this->booking->customer_name},")
            ->line('Your booking is now assigned and ready for your journey.')
            ->line('Status: Assigned')
            ->line("Driver: {$driver->first_name} {$driver->last_name}")
            ->line("Driver phone: {$driver->phone}")
            ->line("Vehicle: {$this->booking->car->displayName()}")
            ->line("Pickup: {$this->booking->pickup_address}")
            ->line("Starts: {$this->booking->starts_at->format('d M Y H:i')}")
            ->line('Please have the QR ticket on your booking page ready when you arrive.')
            ->action('View updated booking', $this->publicUrl)
            ->line('If you have any questions, reply to this email or contact our support team.');
    }
}
