<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Booking;

final readonly class BookingCreationResult
{
    public function __construct(
        public Booking $booking,
        public string $secureToken,
        public bool $created,
    ) {}
}
