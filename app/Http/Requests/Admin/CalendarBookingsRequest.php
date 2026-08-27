<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CalendarBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Booking::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
        ];
    }
}
