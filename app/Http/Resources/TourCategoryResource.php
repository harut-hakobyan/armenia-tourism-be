<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TourCategoryResource extends JsonResource
{
    use ResolvesTranslation;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $translation = $this->translation();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'locale' => $translation?->locale,
            'name' => $translation?->name,
            'description' => $translation?->description,
            'seo' => [
                'title' => $translation?->seo_title,
                'description' => $translation?->seo_description,
            ],
        ];
    }
}
