<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $media = $this->whenLoaded('media');

        return [
            'id' => $this->id,
            'name' => "{$this->brand} {$this->model}",
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'category' => $this->category->value,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'transmission' => $this->transmission,
            'features' => [
                'air_conditioning' => $this->air_conditioning,
                'wifi' => $this->wifi,
                'child_seat_available' => $this->child_seat_available,
            ],
            'rates' => [
                'base_minor' => $this->base_price_minor,
                'per_km_minor' => $this->price_per_km_minor,
                'per_hour_minor' => $this->price_per_hour_minor,
                'currency' => $this->currency->value,
            ],
            'cover_image' => $this->when(
                $this->relationLoaded('media'),
                fn () => ($cover = $media->where('collection', 'cover')->last()
                    ?? $media->where('collection', 'gallery')->last()) ? new MediaResource($cover) : null,
            ),
            'gallery' => $this->when(
                $this->relationLoaded('media'),
                fn () => MediaResource::collection($media->where('collection', 'gallery')->values()),
            ),
        ];
    }
}
