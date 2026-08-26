<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TourDay extends Model
{
    protected $fillable = ['tour_id', 'day_number', 'title', 'description', 'overnight_location'];

    protected function casts(): array
    {
        return ['day_number' => 'integer'];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TourStop::class)->orderBy('stop_order');
    }
}
