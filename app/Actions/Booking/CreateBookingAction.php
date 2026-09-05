<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Contracts\RouteCalculationService;
use App\Data\BookingCreationResult;
use App\Data\CreateBookingData;
use App\Data\PriceBreakdown;
use App\Data\RoutePoint;
use App\Data\RouteResult;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\TourFormat;
use App\Events\BookingCreated;
use App\Exceptions\BookingUnavailableException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Booking;
use App\Models\Car;
use App\Models\CustomTripBookingDetail;
use App\Models\PromoCode;
use App\Models\Tour;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingAccessTokenService;
use App\Services\Booking\BookingCheckInTokenService;
use App\Services\Booking\BookingNumberGenerator;
use App\Services\Booking\CustomerResolver;
use App\Services\Pricing\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBookingAction
{
    public function __construct(
        private readonly RouteCalculationService $routing,
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
        private readonly BookingNumberGenerator $numbers,
        private readonly BookingAccessTokenService $tokens,
        private readonly BookingCheckInTokenService $checkInTokens,
        private readonly CustomerResolver $customers,
    ) {}

    public function execute(CreateBookingData $data): BookingCreationResult
    {
        $fingerprint = $data->fingerprint();

        if ($existing = Booking::query()->where('idempotency_key', $data->idempotencyKey)->first()) {
            return $this->existingResult($existing, $fingerprint);
        }

        // External routing providers belong outside the transaction and database locks.
        $route = in_array($data->serviceType, [ServiceType::AirportTransfer, ServiceType::CustomTrip], true)
            ? $this->routing->calculateRoute($data->routePoints)
            : null;

        return DB::transaction(function () use ($data, $fingerprint, $route): BookingCreationResult {
            DB::table('booking_idempotency_keys')->insertOrIgnore([
                'key' => $data->idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $claim = DB::table('booking_idempotency_keys')
                ->where('key', $data->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if (! hash_equals($claim->request_fingerprint, $fingerprint)) {
                throw new IdempotencyConflictException('The idempotency key was already used with different booking data.');
            }

            if ($claim->booking_id) {
                return $this->existingResult(Booking::query()->findOrFail($claim->booking_id), $fingerprint);
            }

            $tour = $data->serviceType === ServiceType::Tour
                ? Tour::query()->with('translations')->lockForUpdate()->findOrFail($data->tourId)
                : null;
            $carId = $data->carId;
            if (! $carId) {
                throw new BookingUnavailableException('A car is required for this booking.');
            }
            $car = Car::query()->where('active', true)->where('available_for_booking', true)
                ->lockForUpdate()->find($carId);
            if (! $car) {
                throw new BookingUnavailableException('The selected vehicle is not available.');
            }
            $startsAt = $tour?->format === TourFormat::Group && $tour->start_time
                ? $data->startsAt->setTimeFromTimeString((string) $tour->start_time)
                : $data->startsAt;
            $endsAt = $data->serviceType === ServiceType::Tour
                ? $startsAt->addMinutes($tour->duration_minutes)
                : $this->plannedEnd($data, $tour, $route);

            $promo = $data->promoCode
                ? PromoCode::query()->where('code', mb_strtoupper(trim($data->promoCode)))->lockForUpdate()->first()
                : null;

            if ($tour?->format !== TourFormat::Group && ! $this->availability->isCarAvailable($car, $startsAt, $endsAt)) {
                throw new BookingUnavailableException('The selected car is no longer available for this time.');
            }

            $price = $this->calculatePrice($data, $car, $tour, $route, $startsAt);
            $customer = $this->customers->resolve($data);
            $uuid = (string) Str::uuid();
            $secureToken = $this->tokens->tokenForUuid($uuid);
            $checkInToken = $this->checkInTokens->tokenForUuid($uuid);

            $booking = Booking::query()->create([
                'uuid' => $uuid,
                'booking_number' => $this->numbers->next($startsAt->year),
                'secure_token_hash' => $this->tokens->hash($secureToken),
                'check_in_token_hash' => $this->checkInTokens->hash($checkInToken),
                'idempotency_key' => $data->idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'customer_id' => $customer->id,
                'tour_id' => $tour?->id,
                'group_tour_departure_id' => null,
                'car_id' => $car->id,
                'driver_id' => null,
                'promo_code_id' => $promo?->id,
                'service_type' => $data->serviceType,
                'booking_date' => $startsAt->toDateString(),
                'pickup_time' => $startsAt->format('H:i:s'),
                'starts_at' => $startsAt,
                'planned_end_at' => $endsAt,
                'pickup_address' => $tour?->format === TourFormat::Group
                    ? ($tour->meeting_point ?? $data->pickupAddress)
                    : $data->pickupAddress,
                'pickup_latitude' => $data->pickupLatitude,
                'pickup_longitude' => $data->pickupLongitude,
                'dropoff_address' => $data->dropoffAddress,
                'dropoff_latitude' => $data->dropoffLatitude,
                'dropoff_longitude' => $data->dropoffLongitude,
                'passengers' => $data->passengers,
                'customer_name' => $data->customerName,
                'customer_email' => $data->normalizedEmail(),
                'customer_phone' => $data->customerPhone,
                'customer_whatsapp' => $data->customerWhatsapp,
                'customer_nationality' => $data->customerNationality,
                'customer_notes' => $data->customerNotes,
                'subtotal_minor' => $price->subtotalMinor,
                'discount_minor' => $price->discountMinor,
                'deposit_amount_minor' => 0,
                'total_minor' => $price->totalMinor,
                'currency' => $price->currency,
                'payment_method' => $data->paymentMethod,
                'payment_status' => PaymentStatus::Unpaid,
                'booking_status' => BookingStatus::Pending,
                'attendance_status' => AttendanceStatus::Expected,
                'checked_in_passengers' => 0,
                'price_breakdown' => $price->toArray(),
            ]);

            $this->storeServiceDetails($booking, $data, $tour, $route);
            $booking->statusHistory()->create([
                'from_status' => null,
                'to_status' => BookingStatus::Pending,
                'note' => 'Booking created.',
            ]);

            DB::table('booking_idempotency_keys')
                ->where('key', $data->idempotencyKey)
                ->update(['booking_id' => $booking->id, 'updated_at' => now()]);

            BookingCreated::dispatch($booking);

            return new BookingCreationResult($this->loadBooking($booking), $secureToken, true);
        }, 3);
    }

    private function existingResult(Booking $booking, string $fingerprint): BookingCreationResult
    {
        if (! hash_equals($booking->request_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException('The idempotency key was already used with different booking data.');
        }

        return new BookingCreationResult(
            $this->loadBooking($booking),
            $this->tokens->tokenForUuid($booking->uuid),
            false,
        );
    }

    private function plannedEnd(CreateBookingData $data, ?Tour $tour, ?RouteResult $route): CarbonImmutable
    {
        $minutes = match ($data->serviceType) {
            ServiceType::Tour => $tour?->duration_minutes,
            ServiceType::AirportTransfer => $route
                ? $route->drivingDurationMinutes + (int) ($data->serviceOptions['extra_waiting_minutes'] ?? 0)
                : null,
            ServiceType::PrivateDriver => $data->durationMinutes,
            ServiceType::CustomTrip => $route?->estimatedTourDurationMinutes,
        };

        if (! $minutes || $minutes < 1) {
            throw new BookingUnavailableException('The selected service has no valid duration.');
        }

        return $data->startsAt->addMinutes($minutes);
    }

    private function calculatePrice(
        CreateBookingData $data,
        Car $car,
        ?Tour $tour,
        ?RouteResult $route,
        CarbonImmutable $startsAt,
    ): PriceBreakdown {
        return match ($data->serviceType) {
            ServiceType::Tour => $this->pricing->calculateTour(
                $tour,
                $car,
                $data->passengers,
                $startsAt,
                $data->promoCode,
                $data->normalizedEmail(),
            ),
            ServiceType::AirportTransfer => $this->pricing->calculateTransfer(
                $car,
                $route->distanceMeters,
                $data->passengers,
                $data->promoCode,
                $data->normalizedEmail(),
            ),
            ServiceType::PrivateDriver => $this->pricing->calculatePrivateDriver(
                $car,
                $data->durationMinutes,
                $data->passengers,
                $data->promoCode,
                $data->normalizedEmail(),
            ),
            ServiceType::CustomTrip => $this->pricing->calculateCustomTrip(
                $car,
                $route->distanceMeters,
                $route->estimatedTourDurationMinutes,
                $data->passengers,
                $data->promoCode,
                $data->normalizedEmail(),
            ),
        };
    }

    private function storeServiceDetails(
        Booking $booking,
        CreateBookingData $data,
        ?Tour $tour,
        ?RouteResult $route,
    ): void {
        match ($data->serviceType) {
            ServiceType::Tour => $booking->tourDetail()->create([
                'tour_id' => $tour->id,
                'tour_snapshot' => [
                    'slug' => $tour->slug,
                    'duration_minutes' => $tour->duration_minutes,
                    'distance_km' => $tour->approximate_distance_km,
                    'pricing_type' => $tour->pricing_type->value,
                    'format' => $tour->format->value,
                    'start_time' => $tour->format === TourFormat::Group
                        ? substr((string) $tour->start_time, 0, 5)
                        : null,
                    'meeting_point' => $tour->format === TourFormat::Group ? $tour->meeting_point : null,
                    'translations' => $tour->translations->mapWithKeys(
                        static fn ($translation): array => [$translation->locale => $translation->title],
                    )->all(),
                ],
            ]),
            ServiceType::AirportTransfer => $booking->transferDetail()->create([
                'flight_number' => $data->serviceOptions['flight_number'] ?? null,
                'arrival_at' => $data->serviceOptions['arrival_at'] ?? null,
                'airport_pickup_sign' => (bool) ($data->serviceOptions['airport_pickup_sign'] ?? false),
                'pickup_sign_name' => $data->serviceOptions['pickup_sign_name'] ?? null,
                'child_seat' => (bool) ($data->serviceOptions['child_seat'] ?? false),
                'extra_waiting_minutes' => (int) ($data->serviceOptions['extra_waiting_minutes'] ?? 0),
                'estimated_distance_meters' => $route->distanceMeters,
                'estimated_duration_minutes' => $route->drivingDurationMinutes,
                'route_snapshot' => $this->routeSnapshot($route),
            ]),
            ServiceType::PrivateDriver => $booking->privateDriverDetail()->create([
                'duration_minutes' => $data->durationMinutes,
                'package_code' => $this->packageCode($data->durationMinutes),
                'desired_destinations' => $data->serviceOptions['desired_destinations'] ?? null,
            ]),
            ServiceType::CustomTrip => $this->storeCustomTripDetails($booking, $data, $route),
        };
    }

    private function storeCustomTripDetails(Booking $booking, CreateBookingData $data, RouteResult $route): CustomTripBookingDetail
    {
        $detail = $booking->customTripDetail()->create([
            'return_to_yerevan' => (bool) ($data->serviceOptions['return_to_yerevan'] ?? false),
            'estimated_distance_meters' => $route->distanceMeters,
            'estimated_driving_minutes' => $route->drivingDurationMinutes,
            'estimated_tour_minutes' => $route->estimatedTourDurationMinutes,
            'route_provider' => $route->provider,
            'route_snapshot' => $this->routeSnapshot($route),
        ]);

        foreach ($data->routePoints as $index => $point) {
            $detail->stops()->create([
                'stop_order' => $index + 1,
                'label' => $point->label,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
            ]);
        }

        return $detail;
    }

    /** @return array<string, mixed> */
    private function routeSnapshot(RouteResult $route): array
    {
        return [
            'provider' => $route->provider,
            'distance_meters' => $route->distanceMeters,
            'driving_duration_minutes' => $route->drivingDurationMinutes,
            'tour_duration_minutes' => $route->estimatedTourDurationMinutes,
            'points' => array_map(
                static fn (RoutePoint $point): array => [
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                    'label' => $point->label,
                ],
                $route->points,
            ),
        ];
    }

    private function packageCode(int $durationMinutes): string
    {
        return match ($durationMinutes) {
            240 => '4_hours',
            480 => '8_hours',
            720 => '12_hours',
            1440 => 'full_day',
            default => 'custom',
        };
    }

    private function loadBooking(Booking $booking): Booking
    {
        return $booking->load([
            'car', 'tour.translations', 'driver', 'promoCode', 'statusHistory',
            'tourDetail', 'transferDetail', 'privateDriverDetail', 'customTripDetail.stops',
        ]);
    }
}
