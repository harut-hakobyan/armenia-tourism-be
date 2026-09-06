<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Data\PriceBreakdown;
use App\Enums\CarType;
use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Enums\TourFormat;
use App\Models\Car;
use App\Models\CarTypePrice;
use App\Models\Tour;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;

final class PricingService
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function calculateTour(
        Tour $tour,
        Car $car,
        int $passengers,
        CarbonImmutable $date,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): PriceBreakdown {
        $this->validateCar($car, $passengers);

        if (! $tour->active) {
            throw new DomainException('The selected tour is not active.');
        }

        if ($tour->max_passengers !== null && $passengers > $tour->max_passengers) {
            throw new InvalidArgumentException('Passenger count exceeds the selected tour capacity.');
        }

        [$carTypePriceMinor, $carCurrency] = $this->typePrice($car);
        if ($tour->currency !== $carCurrency) {
            throw new DomainException('Tour and car currencies do not match.');
        }

        $baseMinor = match ($tour->pricing_type) {
            PricingType::PerCar, PricingType::Fixed => $tour->starting_price_minor,
            PricingType::PerPerson => $tour->starting_price_minor * $passengers,
            PricingType::Custom => throw new DomainException('This tour requires a custom quote.'),
        };

        $adjustments = [];

        if ($tour->format === TourFormat::Private) {
            [$sedanPriceMinor, $sedanCurrency] = $this->typePrice(CarType::Sedan);
            if ($sedanCurrency !== $carCurrency) {
                throw new DomainException('Vehicle type currencies do not match.');
            }
            if ($carTypePriceMinor !== $sedanPriceMinor) {
                $adjustments['car_type'] = $carTypePriceMinor - $sedanPriceMinor;
            }
        }

        return $this->buildBreakdown($baseMinor, $adjustments, $tour->currency, $promoCode, $customerEmail);
    }

    public function calculateCustomTrip(
        Car $car,
        int $distanceMeters,
        int $durationMinutes,
        int $passengers = 1,
        ?string $promoCode = null,
        ?string $customerEmail = null,
        bool $allowMultipleVehicles = true,
    ): PriceBreakdown {
        if ($allowMultipleVehicles) {
            $this->validateCarForMultipleVehicles($car, $passengers);
        } else {
            $this->validateCar($car, $passengers);
        }
        $this->validateMeasurements($distanceMeters, $durationMinutes);
        [$fixedPriceMinor, $currency] = $this->typePrice($car);
        $vehicleCount = $allowMultipleVehicles
            ? $this->customTripVehicleCount($car, $passengers)
            : 1;

        return $this->buildBreakdown(
            $fixedPriceMinor,
            $vehicleCount > 1
                ? ['additional_vehicles' => ($vehicleCount - 1) * $fixedPriceMinor]
                : [],
            $currency,
            $promoCode,
            $customerEmail,
        );
    }

    public function customTripVehicleCount(Car $car, int $passengers): int
    {
        if ($passengers < 1) {
            throw new InvalidArgumentException('At least one passenger is required.');
        }

        return intdiv($passengers + $car->passenger_capacity - 1, $car->passenger_capacity);
    }

    public function calculateTransfer(
        Car $car,
        int $distanceMeters,
        int $passengers = 1,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): PriceBreakdown {
        $this->validateCar($car, $passengers);
        $this->validateMeasurements($distanceMeters, 0);
        [$fixedPriceMinor, $currency] = $this->typePrice($car);

        return $this->buildBreakdown(
            $fixedPriceMinor,
            [],
            $currency,
            $promoCode,
            $customerEmail,
        );
    }

    public function calculatePrivateDriver(
        Car $car,
        int $durationMinutes,
        int $passengers = 1,
        ?string $promoCode = null,
        ?string $customerEmail = null,
    ): PriceBreakdown {
        $this->validateCar($car, $passengers);
        $this->validateMeasurements(0, $durationMinutes);
        [$fixedPriceMinor, $currency] = $this->typePrice($car);

        return $this->buildBreakdown(
            $fixedPriceMinor,
            [],
            $currency,
            $promoCode,
            $customerEmail,
        );
    }

    /** @param array<string, int> $adjustments */
    private function buildBreakdown(
        int $baseMinor,
        array $adjustments,
        CurrencyCode $currency,
        ?string $promoCode,
        ?string $customerEmail,
    ): PriceBreakdown {
        $subtotalMinor = max(0, $baseMinor + array_sum($adjustments));
        $promotion = $promoCode
            ? $this->promotions->calculateDiscount($promoCode, $subtotalMinor, $currency, $customerEmail)
            : null;
        $discountMinor = $promotion?->discountMinor ?? 0;

        return new PriceBreakdown(
            baseMinor: $baseMinor,
            adjustments: $adjustments,
            subtotalMinor: $subtotalMinor,
            discountMinor: $discountMinor,
            totalMinor: max(0, $subtotalMinor - $discountMinor),
            currency: $currency,
            promoCode: $promotion?->code,
        );
    }

    private function validateCar(Car $car, int $passengers): void
    {
        if (! $car->active || ! $car->available_for_booking) {
            throw new DomainException('The selected car is not available for booking.');
        }

        if ($passengers < 1 || $passengers > $car->passenger_capacity) {
            throw new InvalidArgumentException('Passenger count exceeds the selected car capacity.');
        }
    }

    private function validateCarForMultipleVehicles(Car $car, int $passengers): void
    {
        if (! $car->active || ! $car->available_for_booking) {
            throw new DomainException('The selected vehicle category is not available for booking.');
        }

        if ($car->passenger_capacity < 1 || $passengers < 1) {
            throw new InvalidArgumentException('At least one passenger is required.');
        }
    }

    private function validateMeasurements(int $distanceMeters, int $durationMinutes): void
    {
        if ($distanceMeters < 0 || $durationMinutes < 0) {
            throw new InvalidArgumentException('Distance and duration cannot be negative.');
        }
    }

    /** @return array{int, CurrencyCode} */
    private function typePrice(Car|CarType $carOrType): array
    {
        $type = $carOrType instanceof Car ? $carOrType->type : $carOrType;
        $price = CarTypePrice::query()->where('type', $type->value)->first();

        return $price
            ? [$price->fixed_price_minor, $price->currency]
            : ($carOrType instanceof Car
                ? [$carOrType->base_price_minor, $carOrType->currency]
                : [0, CurrencyCode::Eur]);
    }
}
