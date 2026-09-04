<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Booking;
use RuntimeException;

final class BookingCheckInTokenService
{
    private const PREFIX = 'AMT-CHECKIN:';

    public function tokenForUuid(string $uuid): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to generate booking check-in tokens.');
        }

        $binary = hash_hmac('sha256', 'booking-check-in:'.$uuid, $key, true);

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public function payload(Booking $booking): string
    {
        return self::PREFIX.$this->tokenForUuid($booking->uuid);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $this->normalize($token));
    }

    public function findBooking(string $payload): Booking
    {
        return Booking::query()
            ->where('check_in_token_hash', $this->hash($payload))
            ->firstOrFail();
    }

    private function normalize(string $payload): string
    {
        $payload = trim($payload);
        if (str_starts_with(mb_strtoupper($payload), self::PREFIX)) {
            $payload = substr($payload, strlen(self::PREFIX));
        }

        return $payload;
    }
}
