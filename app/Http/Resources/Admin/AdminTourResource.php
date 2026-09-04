<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminTourResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cover = $this->media->where('collection', 'cover')->last()
            ?? $this->media->where('collection', 'gallery')->last();

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'slug' => $this->slug,
            'duration_minutes' => $this->duration_minutes,
            'approximate_distance_km' => $this->approximate_distance_km,
            'starting_price_minor' => $this->starting_price_minor,
            'currency' => $this->currency->value,
            'pricing_type' => $this->pricing_type->value,
            'format' => $this->format->value,
            'active' => $this->active,
            'featured' => $this->featured,
            'max_passengers' => $this->max_passengers,
            'pickup_available' => $this->pickup_available,
            'dropoff_available' => $this->dropoff_available,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'sort_order' => $this->sort_order,
            'translations' => $this->translations->map->only([
                'locale', 'title', 'short_description', 'description', 'seo_title', 'seo_description',
            ])->values(),
            'cover_image' => $cover ? new MediaResource($cover) : null,
            'gallery' => MediaResource::collection(
                $this->media->where('collection', 'gallery')->values(),
            ),
        ];
    }
}
