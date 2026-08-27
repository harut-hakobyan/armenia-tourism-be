<?php

declare(strict_types=1);

namespace App\Http\Requests\Driver;

use App\Enums\DriverTripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateDriverTripStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateDriverStatus', $this->route('booking'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DriverTripStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
