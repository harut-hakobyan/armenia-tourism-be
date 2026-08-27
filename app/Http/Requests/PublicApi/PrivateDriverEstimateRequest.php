<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use Illuminate\Foundation\Http\FormRequest;

final class PrivateDriverEstimateRequest extends FormRequest
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
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'duration_minutes' => ['required', 'integer', 'min:60', 'max:1440'],
            'passengers' => ['required', 'integer', 'min:1', 'max:20'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
