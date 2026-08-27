<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...(new BookingResource($this->resource))->toArray($request),
            'customer_id' => $this->customer_id,
            'tour_id' => $this->tour_id,
            'car_id' => $this->car_id,
            'driver_id' => $this->driver_id,
            'promo_code_id' => $this->promo_code_id,
            'admin_notes' => $this->admin_notes,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
