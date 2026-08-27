<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class AssignBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('assign', $this->route('booking'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
