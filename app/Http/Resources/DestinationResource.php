<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DestinationResource extends JsonResource
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
            'name' => $translation?->name,
            'short_description' => $translation?->short_description,
            'description' => $translation?->description,
            'coordinates' => [
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'address' => $this->address,
            'featured' => $this->featured,
            'cover_image' => $this->when(
                $this->relationLoaded('media'),
                fn () => ($cover = $media->firstWhere('collection', 'cover')) ? new MediaResource($cover) : null,
            ),
            'gallery' => $this->when(
                $this->relationLoaded('media'),
                fn () => MediaResource::collection($media->where('collection', 'gallery')->values()),
            ),
            'seo' => [
                'title' => $translation?->seo_title,
                'description' => $translation?->seo_description,
            ],
        ];
    }
}
