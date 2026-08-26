<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Exceptions\InvalidBookingStatusTransition;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class BookingStatusTransitionService
{
    /** @var array<string, list<BookingStatus>> */
    private const ALLOWED_TRANSITIONS = [
        'pending' => [BookingStatus::Confirmed, BookingStatus::Cancelled],
        'confirmed' => [BookingStatus::Assigned, BookingStatus::Cancelled, BookingStatus::NoShow],
        'assigned' => [BookingStatus::DriverOnTheWay, BookingStatus::Cancelled, BookingStatus::NoShow],
        'driver_on_the_way' => [BookingStatus::DriverArrived, BookingStatus::Cancelled],
        'driver_arrived' => [BookingStatus::InProgress, BookingStatus::NoShow],
        'in_progress' => [BookingStatus::Completed],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public function transition(
        Booking $booking,
        BookingStatus $toStatus,
        ?User $actor = null,
        ?string $note = null,
        ?string $ipAddress = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $toStatus, $actor, $note, $ipAddress): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $fromStatus = $locked->booking_status;

            if (! in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus->value], true)) {
                throw new InvalidBookingStatusTransition(
                    "Booking cannot move from {$fromStatus->value} to {$toStatus->value}.",
                );
            }

            if ($toStatus === BookingStatus::Assigned && ! $locked->driver_id) {
                throw new InvalidBookingStatusTransition('A driver must be assigned before the booking can become assigned.');
            }

            $locked->update(['booking_status' => $toStatus]);
            $locked->statusHistory()->create([
                'user_id' => $actor?->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'ip_address' => $ipAddress,
            ]);

            BookingStatusChanged::dispatch($locked, $fromStatus, $toStatus);

            return $locked->refresh();
        }, 3);
    }
}
