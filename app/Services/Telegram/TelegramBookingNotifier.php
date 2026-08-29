<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\UserRole;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Booking;
use App\Models\User;

final class TelegramBookingNotifier
{
    public function bookingCreated(Booking $booking): void
    {
        if (! config('tourism.telegram.bot_token')) {
            return;
        }
        $booking->loadMissing(['tour.translations', 'car']);
        User::query()->whereIn('role', [UserRole::Admin, UserRole::Manager])
            ->where('is_active', true)->whereNotNull('telegram_chat_id')
            ->where('telegram_notifications_enabled', true)->each(function (User $user) use ($booking): void {
                SendTelegramMessageJob::dispatch((string) $user->telegram_chat_id, $this->summary($booking), [
                    [['text' => '✅ Confirm', 'callback_data' => "bc:confirm:{$booking->id}"], ['text' => '❌ Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
                    [['text' => 'View details', 'callback_data' => "bc:detail:{$booking->id}"]],
                ]);
            });
    }

    public function driverAssigned(Booking $booking): void
    {
        if (! config('tourism.telegram.bot_token')) {
            return;
        }
        $booking->loadMissing(['tour.translations', 'car', 'driver.user']);
        $user = $booking->driver?->user;
        if (! $user?->telegram_chat_id || ! $user->telegram_notifications_enabled) {
            return;
        }
        SendTelegramMessageJob::dispatch((string) $user->telegram_chat_id, "<b>New assigned trip</b>\n\n".$this->summary($booking), [
            [['text' => '🚗 On the way', 'callback_data' => "ds:{$booking->id}:on_the_way"]],
            [['text' => 'Trip details', 'callback_data' => "bd:{$booking->id}"]],
        ]);
    }

    public function summary(Booking $booking): string
    {
        $tour = $booking->tour?->translations->firstWhere('locale', 'en')?->title
            ?? $booking->tour?->translations->first()?->title
            ?? ucfirst(str_replace('_', ' ', $booking->service_type->value));

        return '<b>'.e($booking->booking_number)."</b>\n"
            .e($tour)."\n"
            .'📅 '.e($booking->starts_at->format('d M Y H:i'))."\n"
            .'👥 '.e((string) $booking->passengers)."\n"
            .'📍 '.e($booking->pickup_address)."\n"
            .'☎️ '.e($booking->customer_phone)."\n"
            .'Status: <b>'.e($booking->booking_status->value).'</b>';
    }
}
