<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminDestinationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cover = $this->media->where('collection', 'cover')->last()
            ?? $this->media->where('collection', 'gallery')->last();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'active' => $this->active,
            'featured' => $this->featured,
            'sort_order' => $this->sort_order,
            'translations' => $this->translations->map->only([
                'locale', 'name', 'short_description', 'description', 'seo_title', 'seo_description',
            ])->values(),
            'cover_image' => $cover ? new MediaResource($cover) : null,
        ];
    }
}
