<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicApi;

use App\Http\Requests\PublicApi\Concerns\BuildsRoutePoints;
use Illuminate\Foundation\Http\FormRequest;

final class TransferEstimateRequest extends FormRequest
{
    use BuildsRoutePoints;

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
            'passengers' => ['required', 'integer', 'min:1', 'max:20'],
            'extra_waiting_minutes' => ['nullable', 'integer', 'min:0', 'max:360'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            ...$this->routePointRules(),
        ];
    }
}
