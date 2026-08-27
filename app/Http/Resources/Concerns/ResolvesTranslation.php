<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

trait ResolvesTranslation
{
    protected function translation(): ?Model
    {
        if (! $this->resource->relationLoaded('translations')) {
            return null;
        }

        $translations = $this->resource->translations;

        return $translations->firstWhere('locale', app()->getLocale())
            ?? $translations->firstWhere('locale', config('app.fallback_locale', 'en'))
            ?? $translations->first();
    }
}
