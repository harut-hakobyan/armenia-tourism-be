<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TourCategoryTranslation extends Model
{
    protected $fillable = [
        'locale', 'name', 'description', 'seo_title', 'seo_description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'tour_category_id');
    }
}
