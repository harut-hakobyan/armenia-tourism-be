<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminCarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cover = $this->media->where('collection', 'cover')->last()
            ?? $this->media->where('collection', 'gallery')->last();

        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'plate_number' => $this->plate_number,
            'color' => $this->color,
            'category' => $this->category->value,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'transmission' => $this->transmission,
            'air_conditioning' => $this->air_conditioning,
            'wifi' => $this->wifi,
            'child_seat_available' => $this->child_seat_available,
            'base_price_minor' => $this->base_price_minor,
            'price_per_km_minor' => $this->price_per_km_minor,
            'price_per_hour_minor' => $this->price_per_hour_minor,
            'currency' => $this->currency->value,
            'active' => $this->active,
            'available_for_booking' => $this->available_for_booking,
            'cover_image' => $cover ? new MediaResource($cover) : null,
        ];
    }
}
