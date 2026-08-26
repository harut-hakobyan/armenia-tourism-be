<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Booking;
use RuntimeException;

final class BookingAccessTokenService
{
    public function tokenForUuid(string $uuid): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to generate booking access tokens.');
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $uuid, $key, true)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function verify(Booking $booking, string $token): bool
    {
        return hash_equals($booking->secure_token_hash, $this->hash($token));
    }
}
