<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use Illuminate\Foundation\Http\FormRequest;

final class TourEstimateRequest extends FormRequest
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
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'passengers' => ['required', 'integer', 'min:1', 'max:20'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
