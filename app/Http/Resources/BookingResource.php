<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'booking_number' => $this->booking_number,
            'service_type' => $this->service_type->value,
            'booking_status' => $this->booking_status->value,
            'driver_trip_status' => $this->driver_trip_status?->value,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method->value,
            'booking_date' => $this->booking_date->toDateString(),
            'pickup_time' => $this->pickup_time,
            'starts_at' => $this->starts_at->toIso8601String(),
            'planned_end_at' => $this->planned_end_at->toIso8601String(),
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'dropoff' => [
                'address' => $this->dropoff_address,
                'latitude' => $this->dropoff_latitude,
                'longitude' => $this->dropoff_longitude,
            ],
            'passengers' => $this->passengers,
            'attendance' => [
                'status' => $this->attendance_status->value,
                'checked_in_passengers' => $this->checked_in_passengers,
                'remaining_passengers' => max(0, $this->passengers - $this->checked_in_passengers),
                'last_checked_in_at' => $this->last_checked_in_at?->toIso8601String(),
            ],
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'whatsapp' => $this->customer_whatsapp,
                'nationality' => $this->customer_nationality,
            ],
            'car' => $this->whenLoaded('car', fn (): array => [
                'id' => $this->car->id,
                'name' => "{$this->car->brand} {$this->car->model}",
                'category' => $this->car->category->value,
            ]),
            'driver' => $this->whenLoaded('driver', fn (): ?array => $this->driver ? [
                'name' => "{$this->driver->first_name} {$this->driver->last_name}",
                'phone' => $this->driver->phone,
            ] : null),
            'price' => [
                'subtotal_minor' => $this->subtotal_minor,
                'discount_minor' => $this->discount_minor,
                'deposit_amount_minor' => $this->deposit_amount_minor,
                'total_minor' => $this->total_minor,
                'currency' => $this->currency->value,
                'breakdown' => $this->price_breakdown,
            ],
            'service_details' => $this->serviceDetails(),
            'status_history' => BookingStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'driver_trip_status_history' => DriverTripStatusHistoryResource::collection(
                $this->whenLoaded('driverTripStatusHistory'),
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function serviceDetails(): mixed
    {
        return match ($this->service_type) {
            ServiceType::Tour => $this->relationLoaded('tourDetail') && $this->tourDetail
                ? ['tour' => $this->tourDetail->tour_snapshot]
                : null,
            ServiceType::AirportTransfer => $this->relationLoaded('transferDetail') && $this->transferDetail
                ? [
                    'flight_number' => $this->transferDetail->flight_number,
                    'arrival_at' => $this->transferDetail->arrival_at?->toIso8601String(),
                    'airport_pickup_sign' => $this->transferDetail->airport_pickup_sign,
                    'pickup_sign_name' => $this->transferDetail->pickup_sign_name,
                    'child_seat' => $this->transferDetail->child_seat,
                    'extra_waiting_minutes' => $this->transferDetail->extra_waiting_minutes,
                    'estimated_distance_meters' => $this->transferDetail->estimated_distance_meters,
                    'estimated_duration_minutes' => $this->transferDetail->estimated_duration_minutes,
                    'route' => $this->transferDetail->route_snapshot,
                ]
                : null,
            ServiceType::PrivateDriver => $this->relationLoaded('privateDriverDetail') && $this->privateDriverDetail
                ? [
                    'duration_minutes' => $this->privateDriverDetail->duration_minutes,
                    'package_code' => $this->privateDriverDetail->package_code,
                    'desired_destinations' => $this->privateDriverDetail->desired_destinations,
                ]
                : null,
            ServiceType::CustomTrip => $this->relationLoaded('customTripDetail') && $this->customTripDetail
                ? [
                    'return_to_yerevan' => $this->customTripDetail->return_to_yerevan,
                    'estimated_distance_meters' => $this->customTripDetail->estimated_distance_meters,
                    'estimated_driving_minutes' => $this->customTripDetail->estimated_driving_minutes,
                    'estimated_tour_minutes' => $this->customTripDetail->estimated_tour_minutes,
                    'route_provider' => $this->customTripDetail->route_provider,
                    'route' => $this->customTripDetail->route_snapshot,
                    'stops' => $this->customTripDetail->stops->map(static fn ($stop): array => [
                        'order' => $stop->stop_order,
                        'label' => $stop->label,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                    ])->all(),
                ]
                : null,
        };
    }
}
