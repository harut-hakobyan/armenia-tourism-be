<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use App\Http\Requests\PublicApi\Concerns\NormalizesQueryBooleans;
use Illuminate\Foundation\Http\FormRequest;

final class ListDestinationsRequest extends FormRequest
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
            'featured' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
