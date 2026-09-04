<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CheckInBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $translation = $this->tour?->translations->firstWhere('locale', app()->getLocale())
            ?? $this->tour?->translations->firstWhere('locale', config('app.fallback_locale', 'en'))
            ?? $this->tour?->translations->first();

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'service_type' => $this->service_type->value,
            'booking_status' => $this->booking_status->value,
            'tour' => $this->tour ? ['id' => $this->tour->id, 'title' => $translation?->title] : null,
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
            ],
            'starts_at' => $this->starts_at->toIso8601String(),
            'pickup_address' => $this->pickup_address,
            'passengers' => $this->passengers,
            'car' => $this->car ? ['id' => $this->car->id, 'name' => $this->car->displayName()] : null,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => "{$this->driver->first_name} {$this->driver->last_name}",
            ] : null,
            'attendance' => [
                'status' => $this->attendance_status->value,
                'checked_in_passengers' => $this->checked_in_passengers,
                'remaining_passengers' => max(0, $this->passengers - $this->checked_in_passengers),
                'last_checked_in_at' => $this->last_checked_in_at?->toIso8601String(),
            ],
            'check_ins' => $this->whenLoaded('checkIns', fn () => $this->checkIns->map(fn ($checkIn): array => [
                'id' => $checkIn->id,
                'passengers_checked_in' => $checkIn->passengers_checked_in,
                'total_checked_in' => $checkIn->total_checked_in,
                'checked_in_at' => $checkIn->checked_in_at->toIso8601String(),
                'checked_in_by' => $checkIn->checkedInBy ? [
                    'id' => $checkIn->checkedInBy->id,
                    'name' => $checkIn->checkedInBy->name,
                    'role' => $checkIn->checkedInBy->role->value,
                ] : null,
            ])->values()),
        ];
    }
}
