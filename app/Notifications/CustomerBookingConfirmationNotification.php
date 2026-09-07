<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
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
        private readonly string $checkInPayload,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $qrCode = new QrCode(
            data: $this->checkInPayload,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 280,
            margin: 12,
        );
        $qrSvg = (new SvgWriter)->write($qrCode)->getString();

        return (new MailMessage)
            ->subject("Booking received — {$this->booking->booking_number}")
            ->view([
                'html' => 'emails.customer-booking-confirmation',
                'text' => 'emails.customer-booking-confirmation-text',
            ], [
                'booking' => $this->booking,
                'publicUrl' => $this->publicUrl,
                'qrSvg' => $qrSvg,
                'checkInPayload' => $this->checkInPayload,
            ]);
    }
}
