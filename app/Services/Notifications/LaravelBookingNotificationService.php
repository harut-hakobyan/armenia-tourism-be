<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\BookingNotificationService;
use App\Models\Booking;
use App\Notifications\AdminNewBookingNotification;
use App\Notifications\CustomerBookingConfirmationNotification;
use App\Services\Booking\BookingAccessTokenService;
use Illuminate\Support\Facades\Notification;

final class LaravelBookingNotificationService implements BookingNotificationService
{
    public function __construct(private readonly BookingAccessTokenService $tokens) {}

    public function sendBookingCreated(Booking $booking): void
    {
        Notification::route('mail', (string) config('tourism.notifications.admin_email'))
            ->notify(new AdminNewBookingNotification($booking));

        if ($booking->customer_email) {
            $token = $this->tokens->tokenForUuid($booking->uuid);
            $publicUrl = rtrim((string) config('app.frontend_url'), '/')
                ."/booking/{$booking->booking_number}/{$token}";

            Notification::route('mail', $booking->customer_email)
                ->notify(new CustomerBookingConfirmationNotification($booking, $publicUrl));
        }
    }
}
