<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Driver|null $driver */
        $driver = $this->route('driver');
        $required = $driver ? 'sometimes' : 'required';
        $activeCar = Rule::exists('cars', 'id')->whereNull('deleted_at');

        return [
            'first_name' => [$required, 'string', 'max:100'],
            'last_name' => [$required, 'string', 'max:100'],
            'phone' => [$required, 'string', 'max:32'],
            'email' => [
                $required, 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($driver?->user_id),
                Rule::unique('drivers', 'email')->ignore($driver?->id),
            ],
            'password' => [$driver ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'locale' => ['sometimes', Rule::in(['en', 'ru', 'hy'])],
            'languages' => ['sometimes', 'array', 'max:10'],
            'languages.*' => ['string', 'distinct', 'max:10'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:80'],
            'license_number' => [$required, 'string', 'max:100', Rule::unique('drivers', 'license_number')->ignore($driver?->id)],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'active' => ['sometimes', 'boolean'],
            'preferred_car_id' => ['nullable', 'integer', $activeCar],
            'car_ids' => ['sometimes', 'array', 'max:50'],
            'car_ids.*' => ['integer', 'distinct', $activeCar],
        ];
    }
}
