<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('booking'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                BookingStatus::Confirmed->value,
                BookingStatus::Cancelled->value,
                BookingStatus::NoShow->value,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
