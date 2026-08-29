<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use App\Http\Requests\PublicApi\Concerns\NormalizesQueryBooleans;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListToursRequest extends FormRequest
{
    use NormalizesQueryBooleans;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeQueryBooleans(['featured']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'in:en,ru,hy'],
            'category' => ['nullable', 'string', 'max:100', 'exists:tour_categories,slug'],
            'format' => ['nullable', Rule::in(['private', 'group'])],
            'featured' => ['nullable', 'boolean'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'duration_asc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
