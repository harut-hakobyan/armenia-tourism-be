<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GroupTourDepartureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booked = (int) $this->bookings
            ->whereNotIn('booking_status', [BookingStatus::Cancelled, BookingStatus::NoShow])
            ->sum('passengers');

        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'meeting_point' => $this->meeting_point,
            'capacity' => $this->capacity,
            'remaining_seats' => max(0, $this->capacity - $booked),
            'price_per_person' => [
                'amount_minor' => $this->price_per_person_minor ?? $this->tour->starting_price_minor,
                'currency' => $this->currency->value,
            ],
        ];
    }
}
