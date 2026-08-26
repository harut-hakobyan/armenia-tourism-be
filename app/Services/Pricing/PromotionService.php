<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Data\PromotionResult;
use App\Enums\BookingStatus;
use App\Enums\CurrencyCode;
use App\Enums\PromoCodeType;
use App\Exceptions\PromotionException;
use App\Models\PromoCode;
use Carbon\CarbonImmutable;

final class PromotionService
{
    public function calculateDiscount(
        string $code,
        int $subtotalMinor,
        CurrencyCode $currency,
        ?string $customerEmail = null,
        ?CarbonImmutable $at = null,
    ): PromotionResult {
        $at ??= CarbonImmutable::now();
        $normalizedCode = mb_strtoupper(trim($code));
        $promotion = PromoCode::query()->where('code', $normalizedCode)->first();

        if (! $promotion || ! $promotion->active) {
            throw new PromotionException('The promo code is invalid or inactive.');
        }

        if ($promotion->currency !== $currency) {
            throw new PromotionException('The promo code is not valid for this currency.');
        }

        if (($promotion->valid_from && $at->isBefore($promotion->valid_from))
            || ($promotion->valid_until && $at->isAfter($promotion->valid_until))) {
            throw new PromotionException('The promo code is outside its validity period.');
        }

        if ($subtotalMinor < $promotion->min_order_minor) {
            throw new PromotionException('The minimum order value for this promo code has not been reached.');
        }

        $countedBookings = $promotion->bookings()->whereNotIn('booking_status', [
            BookingStatus::Cancelled->value,
            BookingStatus::NoShow->value,
        ]);

        if ($promotion->usage_limit !== null && (clone $countedBookings)->count() >= $promotion->usage_limit) {
            throw new PromotionException('The promo code usage limit has been reached.');
        }

        if ($promotion->usage_per_customer !== null) {
            if (! $customerEmail) {
                throw new PromotionException('An email address is required to use this promo code.');
            }

            $customerUsage = (clone $countedBookings)
                ->where('customer_email', mb_strtolower(trim($customerEmail)))
                ->count();

            if ($customerUsage >= $promotion->usage_per_customer) {
                throw new PromotionException('This customer has reached the promo code usage limit.');
            }
        }

        $discountMinor = match ($promotion->type) {
            PromoCodeType::Percentage => intdiv($subtotalMinor * min($promotion->value, 10_000), 10_000),
            PromoCodeType::Fixed => min($subtotalMinor, $promotion->value),
        };

        if ($promotion->max_discount_minor !== null) {
            $discountMinor = min($discountMinor, $promotion->max_discount_minor);
        }

        return new PromotionResult(
            promoCodeId: $promotion->id,
            code: $promotion->code,
            discountMinor: $discountMinor,
        );
    }
}
