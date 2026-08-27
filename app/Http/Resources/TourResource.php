<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TourResource extends JsonResource
{
    use ResolvesTranslation;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $translation = $this->translation();
        $media = $this->whenLoaded('media');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'locale' => $translation?->locale,
            'title' => $translation?->title,
            'short_description' => $translation?->short_description,
            'description' => $translation?->description,
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category ? new TourCategoryResource($this->category) : null,
            ),
            'duration_minutes' => $this->duration_minutes,
            'approximate_distance_km' => $this->approximate_distance_km,
            'starting_price' => [
                'amount_minor' => $this->starting_price_minor,
                'currency' => $this->currency->value,
                'pricing_type' => $this->pricing_type->value,
            ],
            'max_passengers' => $this->max_passengers,
            'pickup_available' => $this->pickup_available,
            'dropoff_available' => $this->dropoff_available,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'featured' => $this->featured,
            'cover_image' => $this->when(
                $this->relationLoaded('media'),
                fn () => ($cover = $media->firstWhere('collection', 'cover')) ? new MediaResource($cover) : null,
            ),
            'gallery' => $this->when(
                $this->relationLoaded('media'),
                fn () => MediaResource::collection($media->where('collection', 'gallery')->values()),
            ),
            'itinerary' => $this->whenLoaded('stops', fn () => $this->stops->map(
                static function ($stop): array {
                    $translations = $stop->destination?->translations;
                    $destination = $translations?->firstWhere('locale', app()->getLocale())
                        ?? $translations?->firstWhere('locale', config('app.fallback_locale', 'en'))
                        ?? $translations?->first();

                    return [
                        'day_number' => $stop->day_number,
                        'stop_order' => $stop->stop_order,
                        'duration_minutes' => $stop->duration_minutes,
                        'optional' => $stop->optional,
                        'notes' => $stop->notes,
                        'destination' => $stop->destination ? [
                            'slug' => $stop->destination->slug,
                            'name' => $destination?->name,
                            'latitude' => $stop->destination->latitude !== null
                                ? (float) $stop->destination->latitude
                                : null,
                            'longitude' => $stop->destination->longitude !== null
                                ? (float) $stop->destination->longitude
                                : null,
                        ] : null,
                    ];
                },
            )),
            'days' => $this->whenLoaded('days', fn () => $this->days->map(static fn ($day): array => [
                'day_number' => $day->day_number,
                'title' => $day->title,
                'description' => $day->description,
                'overnight_location' => $day->overnight_location,
            ])),
            'seo' => [
                'title' => $translation?->seo_title,
                'description' => $translation?->seo_description,
            ],
        ];
    }
}
