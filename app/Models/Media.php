<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'uuid', 'collection', 'disk', 'path', 'file_name', 'mime_type',
        'size_bytes', 'alt_text', 'sort_order',
    ];

    protected static function booted(): void
    {
        self::creating(static function (Media $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
