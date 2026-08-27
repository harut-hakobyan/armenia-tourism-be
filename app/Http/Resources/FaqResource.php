<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FaqResource extends JsonResource
{
    use ResolvesTranslation;

    public function toArray(Request $request): array
    {
        $translation = $this->translation();

        return [
            'id' => $this->id,
            'category' => $this->category,
            'question' => $translation?->question,
            'answer' => $translation?->answer,
            'sort_order' => $this->sort_order,
        ];
    }
}
