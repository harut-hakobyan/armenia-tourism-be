<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Enums\TourFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $tourId = $this->route('tour')?->getKey() ?? $this->route('id');

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('tour_categories', 'id')->whereNull('deleted_at')],
            'slug' => [$required, 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('tours', 'slug')->ignore($tourId)],
            'duration_minutes' => [$required, 'integer', 'min:1', 'max:43200'],
            'approximate_distance_km' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starting_price_minor' => [$required, 'integer', 'min:0'],
            'currency' => [$required, Rule::enum(CurrencyCode::class)],
            'pricing_type' => [$required, Rule::enum(PricingType::class)],
            'format' => [$required, Rule::enum(TourFormat::class)],
            'start_time' => ['nullable', 'required_if:format,group', 'date_format:H:i'],
            'meeting_point' => ['nullable', 'required_if:format,group', 'string', 'max:255'],
            'active' => [$required, 'boolean'],
            'featured' => [$required, 'boolean'],
            'max_passengers' => ['nullable', 'integer', 'min:1', 'max:255'],
            'pickup_available' => [$required, 'boolean'],
            'dropoff_available' => [$required, 'boolean'],
            'free_cancellation_hours' => [$required, 'integer', 'min:0', 'max:8760'],
            'sort_order' => [$required, 'integer', 'min:0', 'max:100000'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', Rule::in(['en', 'ru', 'hy']), 'distinct'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.short_description' => ['nullable', 'string', 'max:5000'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
