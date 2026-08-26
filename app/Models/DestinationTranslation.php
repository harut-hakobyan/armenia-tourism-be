<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DestinationTranslation extends Model
{
    protected $fillable = [
        'locale', 'name', 'short_description', 'description', 'seo_title', 'seo_description',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
