<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FaqTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['locale', 'question', 'answer'];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }
}
