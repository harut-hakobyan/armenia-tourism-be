<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use App\Enums\CarCategory;
use App\Http\Requests\PublicApi\Concerns\NormalizesQueryBooleans;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCarsRequest extends FormRequest
{
    use NormalizesQueryBooleans;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeQueryBooleans(['child_seat']);
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
