<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Data\PriceBreakdown;
use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Models\Car;
use App\Models\CarCategoryPrice;
use App\Models\Tour;
use App\Models\TourPrice;
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

        [, $carCurrency] = $this->categoryPrice($car);
        if ($tour->currency !== $carCurrency) {
            throw new DomainException('Tour and car currencies do not match.');
        }

        $baseMinor = match ($tour->pricing_type) {
            PricingType::PerCar, PricingType::Fixed => $tour->starting_price_minor,
            PricingType::PerPerson => $tour->starting_price_minor * $passengers,
            PricingType::Custom => throw new DomainException('This tour requires a custom quote.'),
        };

        $rule = $this->matchingTourPrice($tour, $car, $passengers, $date);
        $adjustments = [];

        if ($rule?->fixed_price_minor !== null) {
            $baseMinor = $rule->fixed_price_minor;
        }

        if ($rule && $rule->adjustment_minor !== 0) {
            $adjustments['car_category'] = $rule->adjustment_minor;
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
    ): PriceBreakdown {
        $this->validateCar($car, $passengers);
        $this->validateMeasurements($distanceMeters, $durationMinutes);
        [$fixedPriceMinor, $currency] = $this->categoryPrice($car);

        return $this->buildBreakdown(
            $fixedPriceMinor,
            [],
            $currency,
            $promoCode,
            $customerEmail,
        );
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
        [$fixedPriceMinor, $currency] = $this->categoryPrice($car);

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
        [$fixedPriceMinor, $currency] = $this->categoryPrice($car);

        return $this->buildBreakdown(
            $fixedPriceMinor,
            [],
            $currency,
            $promoCode,
            $customerEmail,
        );
    }

    private function matchingTourPrice(Tour $tour, Car $car, int $passengers, CarbonImmutable $date): ?TourPrice
    {
        return $tour->prices()
            ->where('active', true)
            ->where('car_category', $car->category->value)
            ->where('currency', $tour->currency->value)
            ->where(fn ($query) => $query->whereNull('min_passengers')->orWhere('min_passengers', '<=', $passengers))
            ->where(fn ($query) => $query->whereNull('max_passengers')->orWhere('max_passengers', '>=', $passengers))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();
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

    private function validateMeasurements(int $distanceMeters, int $durationMinutes): void
    {
        if ($distanceMeters < 0 || $durationMinutes < 0) {
            throw new InvalidArgumentException('Distance and duration cannot be negative.');
        }
    }

    /** @return array{int, CurrencyCode} */
    private function categoryPrice(Car $car): array
    {
        $price = CarCategoryPrice::query()->where('category', $car->category->value)->first();

        return $price
            ? [$price->fixed_price_minor, $price->currency]
            : [$car->base_price_minor, $car->currency];
    }
}
