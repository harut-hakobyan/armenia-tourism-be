<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckInBookingAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(
        Booking $booking,
        User $actor,
        ?int $passengers = null,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $actor, $passengers, $notes, $ipAddress): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (in_array($locked->booking_status, [BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
                throw ValidationException::withMessages([
                    'token' => ['Cancelled and no-show bookings cannot be checked in.'],
                ]);
            }

            $remaining = max(0, $locked->passengers - $locked->checked_in_passengers);
            if ($remaining === 0) {
                return $locked->load($this->relations());
            }

            $count = $passengers ?? $remaining;
            if ($count < 1 || $count > $remaining) {
                throw ValidationException::withMessages([
                    'passengers' => ["Enter between 1 and {$remaining} remaining passengers."],
                ]);
            }

            $old = [
                'attendance_status' => $locked->attendance_status->value,
                'checked_in_passengers' => $locked->checked_in_passengers,
                'last_checked_in_at' => $locked->last_checked_in_at?->toIso8601String(),
            ];
            $total = $locked->checked_in_passengers + $count;
            $status = $total >= $locked->passengers
                ? AttendanceStatus::CheckedIn
                : AttendanceStatus::PartiallyCheckedIn;
            $checkedInAt = now();

            $locked->update([
                'attendance_status' => $status,
                'checked_in_passengers' => $total,
                'last_checked_in_at' => $checkedInAt,
            ]);
            $locked->checkIns()->create([
                'checked_in_by_user_id' => $actor->id,
                'passengers_checked_in' => $count,
                'total_checked_in' => $total,
                'checked_in_at' => $checkedInAt,
                'method' => 'qr',
                'notes' => $notes,
                'ip_address' => $ipAddress,
            ]);
            $this->audit->record($actor, 'booking.checked_in', $locked, $old, [
                'attendance_status' => $status->value,
                'checked_in_passengers' => $total,
                'passengers_added' => $count,
            ], $ipAddress);

            return $locked->refresh()->load($this->relations());
        }, 3);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['tour.translations', 'car', 'driver', 'checkIns.checkedInBy'];
    }
}
