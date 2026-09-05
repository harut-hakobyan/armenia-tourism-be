<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Data\CreateBookingData;
use App\Data\RoutePoint;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'tour_id' => ['nullable', 'required_if:service_type,tour', 'integer', 'exists:tours,id'],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'passengers' => ['required', 'integer', 'min:1', 'max:20'],
            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'dropoff_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_whatsapp' => ['nullable', 'string', 'max:32'],
            'customer_nationality' => ['nullable', 'string', 'max:100'],
            'customer_notes' => ['nullable', 'string', 'max:3000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['nullable', 'required_if:service_type,private_driver', 'integer', 'min:60', 'max:1440'],
            'route_points' => ['nullable', 'required_if:service_type,airport_transfer,custom_trip', 'array', 'min:2', 'max:20'],
            'route_points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'route_points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'route_points.*.label' => ['nullable', 'string', 'max:255'],
            'service_options' => ['sometimes', 'array'],
            'service_options.flight_number' => ['nullable', 'string', 'max:50'],
            'service_options.arrival_at' => ['nullable', 'date'],
            'service_options.airport_pickup_sign' => ['sometimes', 'boolean'],
            'service_options.pickup_sign_name' => ['nullable', 'string', 'max:255'],
            'service_options.child_seat' => ['sometimes', 'boolean'],
            'service_options.extra_waiting_minutes' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'service_options.return_to_yerevan' => ['sometimes', 'boolean'],
            'service_options.desired_destinations' => ['sometimes', 'array', 'max:20'],
            'service_options.desired_destinations.*' => ['string', 'max:255'],
        ];
    }

    public function toData(): CreateBookingData
    {
        $validated = $this->validated();
        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$validated['booking_date']} {$validated['pickup_time']}",
            config('app.timezone'),
        );

        return new CreateBookingData(
            idempotencyKey: $validated['idempotency_key'],
            serviceType: ServiceType::from($validated['service_type']),
            carId: isset($validated['car_id']) ? (int) $validated['car_id'] : null,
            tourId: isset($validated['tour_id']) ? (int) $validated['tour_id'] : null,
            startsAt: $startsAt,
            passengers: (int) $validated['passengers'],
            pickupAddress: $validated['pickup_address'],
            pickupLatitude: isset($validated['pickup_latitude']) ? (float) $validated['pickup_latitude'] : null,
            pickupLongitude: isset($validated['pickup_longitude']) ? (float) $validated['pickup_longitude'] : null,
            dropoffAddress: $validated['dropoff_address'] ?? null,
            dropoffLatitude: isset($validated['dropoff_latitude']) ? (float) $validated['dropoff_latitude'] : null,
            dropoffLongitude: isset($validated['dropoff_longitude']) ? (float) $validated['dropoff_longitude'] : null,
            customerName: $validated['customer_name'],
            customerEmail: $validated['customer_email'] ?? null,
            customerPhone: $validated['customer_phone'],
            customerWhatsapp: $validated['customer_whatsapp'] ?? null,
            customerNationality: $validated['customer_nationality'] ?? null,
            customerNotes: $validated['customer_notes'] ?? null,
            paymentMethod: PaymentMethod::from($validated['payment_method']),
            promoCode: $validated['promo_code'] ?? null,
            customerId: $this->user()?->customer?->id,
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            routePoints: array_map(
                static fn (array $point): RoutePoint => new RoutePoint(
                    (float) $point['latitude'],
                    (float) $point['longitude'],
                    $point['label'] ?? null,
                ),
                $validated['route_points'] ?? [],
            ),
            serviceOptions: $validated['service_options'] ?? [],
        );
    }
}
