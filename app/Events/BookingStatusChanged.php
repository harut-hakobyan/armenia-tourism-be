<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BookingStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $fromStatus,
        public readonly BookingStatus $toStatus,
    ) {}
}
