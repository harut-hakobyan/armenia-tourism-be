<?php

declare(strict_types=1);

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBookingCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
