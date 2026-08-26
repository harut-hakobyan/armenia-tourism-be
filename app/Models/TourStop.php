<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TourStop extends Model
{
    protected $fillable = [
        'tour_id', 'tour_day_id', 'destination_id', 'day_number', 'stop_order',
        'duration_minutes', 'optional', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'stop_order' => 'integer',
            'duration_minutes' => 'integer',
            'optional' => 'boolean',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TourDay::class, 'tour_day_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
