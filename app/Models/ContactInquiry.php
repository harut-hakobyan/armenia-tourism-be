<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactInquiry extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'handled_at', 'handled_by'];

    protected function casts(): array
    {
        return ['handled_at' => 'immutable_datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
