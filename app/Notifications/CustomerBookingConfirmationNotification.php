<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CustomerBookingConfirmationNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Booking received — {$this->booking->booking_number}")
            ->greeting("Hello {$this->booking->customer_name},")
            ->line('We received your Armenia travel booking and will confirm it shortly.')
            ->line("Booking number: {$this->booking->booking_number}")
            ->line("Passengers: {$this->booking->passengers}")
            ->line("Pickup: {$this->booking->pickup_address}")
            ->line("Starts: {$this->booking->starts_at->format('d M Y H:i')}")
            ->line('Total: '.number_format($this->booking->total_minor / 100, 2).' '.$this->booking->currency->value)
            ->line('Your secure booking page includes the QR ticket that staff will scan when you arrive.')
            ->action('View your booking', $this->publicUrl)
            ->line('Keep this private link safe; it provides access to your booking details.');
    }
}
