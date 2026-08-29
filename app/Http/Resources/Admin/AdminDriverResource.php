<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminDriverResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->media->where('collection', 'profile')->last();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'locale' => $this->user?->locale ?? 'en',
            'languages' => $this->languages ?? [],
            'experience_years' => $this->experience_years,
            'license_number' => $this->license_number,
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'active' => $this->active,
            'preferred_car_id' => $this->preferred_car_id,
            'car_ids' => $this->cars->pluck('id')->values(),
            'cars' => $this->cars->map(fn ($car): array => [
                'id' => $car->id,
                'name' => "{$car->brand} {$car->model}",
                'plate_number' => $car->plate_number,
            ])->values(),
            'profile_image' => $profile ? new MediaResource($profile) : null,
            'telegram_connected' => $this->user?->telegram_chat_id !== null,
        ];
    }
}
