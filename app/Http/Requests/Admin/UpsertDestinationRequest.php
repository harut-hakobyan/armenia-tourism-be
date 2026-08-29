<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $destinationId = $this->route('destination')?->getKey() ?? $this->route('id');

        return [
            'slug' => [$required, 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('destinations', 'slug')->ignore($destinationId)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => [$required, 'boolean'],
            'featured' => [$required, 'boolean'],
            'sort_order' => [$required, 'integer', 'min:0', 'max:100000'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in(['en', 'ru', 'hy']), 'distinct'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:5000'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
