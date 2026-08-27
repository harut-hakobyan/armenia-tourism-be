<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Review extends Model
{
    protected $fillable = ['booking_id', 'customer_id', 'customer_name', 'rating', 'title', 'review', 'active', 'verified'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'active' => 'boolean', 'verified' => 'boolean'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
