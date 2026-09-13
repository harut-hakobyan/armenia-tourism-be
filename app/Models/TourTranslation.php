<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TourTranslation extends Model
{
    protected $fillable = [
        'locale', 'title', 'short_description', 'description', 'inclusions', 'exclusions',
        'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'inclusions' => 'array',
            'exclusions' => 'array',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
