<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Booking::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_status' => ['nullable', Rule::enum(BookingStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
