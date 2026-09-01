<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\BookingNotificationService;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\AdminNewBookingNotification;
use App\Notifications\CustomerBookingConfirmationNotification;
use App\Notifications\CustomerDriverAssignedNotification;
use App\Notifications\DriverAssignedNotification;
use App\Services\Booking\BookingAccessTokenService;
use App\Services\Telegram\TelegramBookingNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class LaravelBookingNotificationService implements BookingNotificationService
{
    public function __construct(
        private readonly BookingAccessTokenService $tokens,
        private readonly TelegramBookingNotifier $telegram,
    ) {}

    public function sendBookingCreated(Booking $booking): void
    {
        $this->staffEmails()->each(
            fn (string $email) => Notification::route('mail', $email)
                ->notify(new AdminNewBookingNotification($booking)),
        );
        $this->telegram->bookingCreated($booking);

        if ($booking->customer_email) {
            Notification::route('mail', $booking->customer_email)
                ->notify(new CustomerBookingConfirmationNotification($booking, $this->publicUrl($booking)));
        }
    }

    public function sendDriverAssigned(Booking $booking): void
    {
        $this->telegram->driverAssigned($booking);
        if ($booking->driver?->email) {
            Notification::route('mail', $booking->driver->email)
                ->notify(new DriverAssignedNotification($booking));
        }

        if ($booking->customer_email) {
            Notification::route('mail', $booking->customer_email)
                ->notify(new CustomerDriverAssignedNotification($booking, $this->publicUrl($booking)));
        }
    }

    /** @return Collection<int, string> */
    private function staffEmails(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->whereNotNull('email')
            ->pluck('email')
            ->push((string) config('tourism.notifications.admin_email'))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();
    }

    private function publicUrl(Booking $booking): string
    {
        $token = $this->tokens->tokenForUuid($booking->uuid);

        return rtrim((string) config('app.frontend_url'), '/')
            ."/booking/{$booking->booking_number}/{$token}";
    }
}
