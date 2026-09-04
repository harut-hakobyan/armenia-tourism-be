<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

final class MediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'collection' => $this->collection,
            'url' => Storage::disk($this->disk)->url($this->path),
            'alt_text' => $this->alt_text,
            'mime_type' => $this->mime_type,
        ];
    }
}
