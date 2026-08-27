<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use App\Enums\CarCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'in:en,ru,hy'],
            'category' => ['nullable', Rule::enum(CarCategory::class)],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:20'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:20'],
            'child_seat' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'capacity_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
