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
        if (! config('tourism.telegram.bot_token')) return;
        $this->loadBooking($booking);
        User::query()->whereIn('role', [UserRole::Admin, UserRole::Manager])->where('is_active', true)->whereNotNull('telegram_chat_id')->where('telegram_notifications_enabled', true)->each(function (User $user) use ($booking): void {
            SendTelegramMessageJob::dispatch((string) $user->telegram_chat_id, $this->summary($booking), [
                [['text' => '✅ Confirm', 'callback_data' => "bc:confirm:{$booking->id}"], ['text' => '❌ Cancel', 'callback_data' => "bc:cancel:{$booking->id}"]],
                [['text' => 'View details', 'callback_data' => "bc:detail:{$booking->id}"]],
            ]);
        });
    }

    public function driverAssigned(Booking $booking): void
    {
        if (! config('tourism.telegram.bot_token')) return;
        $this->loadBooking($booking);
        $user = $booking->driver?->user;
        if (! $user?->telegram_chat_id || ! $user->telegram_notifications_enabled) return;
        SendTelegramMessageJob::dispatch((string) $user->telegram_chat_id, "<b>New assigned trip</b>\n\n".$this->summary($booking), [
            [['text' => '🚗 On the way', 'callback_data' => "ds:{$booking->id}:on_the_way"]],
            [['text' => 'Trip details', 'callback_data' => "bd:{$booking->id}"]],
        ]);
    }

    public function summary(Booking $booking): string
    {
        $tour = $booking->tour?->translations->firstWhere('locale', 'en')?->title ?? $booking->tour?->translations->first()?->title ?? ucfirst(str_replace('_', ' ', $booking->service_type->value));
        $price = number_format($booking->total_minor / 100, 2).' '.($booking->currency?->value ?? '');
        return '<b>'.e($booking->booking_number)."</b>\n"
            .'Tour Type: '.e($tour)."\nCustomer Name: ".e($booking->customer_name ?: '—')."\nCustomer Phone: ".e($booking->customer_phone ?: '—')."\nCustomer Email: ".e($booking->customer_email ?: '—')."\nCustomer WhatsApp: ".e($booking->customer_whatsapp ?: '—')."\nTour Price: ".e($price)."\nDate of Tour: ".e($booking->starts_at->format('d M Y H:i'))."\nPickup Location: ".e($booking->pickup_address ?: '—')."\nPassenger Count: ".e((string) $booking->passengers)."\nDestinations: ".e($this->destinations($booking))."\nStatus: <b>".e($booking->booking_status->value).'</b>';
    }

    private function loadBooking(Booking $booking): void
    {
        $booking->loadMissing(['tour.translations', 'tour.stops.destination.translations', 'tourDetail', 'privateDriverDetail', 'car', 'driver.user']);
    }

    private function destinations(Booking $booking): string
    {
        $custom = $booking->privateDriverDetail?->desired_destinations;
        if (is_array($custom) && $custom !== []) return implode(', ', array_map('strval', $custom));
        return $booking->tour?->stops->map(fn ($stop) => $stop->destination?->translations->firstWhere('locale', 'en')?->name ?? $stop->destination?->translations->first()?->name)->filter()->unique()->values()->implode(', ') ?: '—';
    }
}
