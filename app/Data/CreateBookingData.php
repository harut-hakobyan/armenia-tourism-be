<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;

final readonly class CreateBookingData
{
    /**
     * @param  list<RoutePoint>  $routePoints
     * @param  array<string, mixed>  $serviceOptions
     */
    public function __construct(
        public string $idempotencyKey,
        public ServiceType $serviceType,
        public ?int $carId,
        public ?int $tourId,
        public CarbonImmutable $startsAt,
        public int $passengers,
        public string $pickupAddress,
        public ?float $pickupLatitude,
        public ?float $pickupLongitude,
        public ?string $dropoffAddress,
        public ?float $dropoffLatitude,
        public ?float $dropoffLongitude,
        public string $customerName,
        public ?string $customerEmail,
        public string $customerPhone,
        public ?string $customerWhatsapp,
        public ?string $customerNationality,
        public ?string $customerNotes,
        public PaymentMethod $paymentMethod,
        public ?string $promoCode,
        public ?int $customerId,
        public ?int $durationMinutes,
        public array $routePoints,
        public array $serviceOptions,
    ) {}

    public function fingerprint(): string
    {
        $payload = [
            'service_type' => $this->serviceType->value,
            'car_id' => $this->carId,
            'tour_id' => $this->tourId,
            'starts_at' => $this->startsAt->toIso8601String(),
            'passengers' => $this->passengers,
            'pickup' => [$this->pickupAddress, $this->pickupLatitude, $this->pickupLongitude],
            'dropoff' => [$this->dropoffAddress, $this->dropoffLatitude, $this->dropoffLongitude],
            'customer' => [
                $this->customerName, $this->normalizedEmail(), $this->customerPhone,
                $this->customerWhatsapp, $this->customerNationality, $this->customerNotes,
            ],
            'payment_method' => $this->paymentMethod->value,
            'promo_code' => $this->promoCode ? mb_strtoupper(trim($this->promoCode)) : null,
            'duration_minutes' => $this->durationMinutes,
            'route_points' => array_map(
                static fn (RoutePoint $point): array => [$point->latitude, $point->longitude, $point->label],
                $this->routePoints,
            ),
            'service_options' => $this->sortRecursively($this->serviceOptions),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function normalizedEmail(): ?string
    {
        return $this->customerEmail ? mb_strtolower(trim($this->customerEmail)) : null;
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sortRecursively(array $values): array
    {
        ksort($values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sortRecursively($value);
            }
        }

        return $values;
    }
}
