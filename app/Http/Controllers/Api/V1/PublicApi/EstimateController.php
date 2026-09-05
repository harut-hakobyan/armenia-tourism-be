<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\CustomTripEstimateRequest;
use App\Http\Requests\PublicApi\PrivateDriverEstimateRequest;
use App\Http\Requests\PublicApi\TourEstimateRequest;
use App\Http\Requests\PublicApi\TransferEstimateRequest;
use App\Models\Car;
use App\Models\Tour;
use App\Services\Pricing\ServiceEstimateService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EstimateController extends Controller
{
    public function tour(TourEstimateRequest $request, ServiceEstimateService $estimates): JsonResponse
    {
        $data = $request->validated();

        $tour = Tour::query()->active()->findOrFail($data['tour_id']);

        if (! isset($data['car_id'])) {
            throw ValidationException::withMessages(['car_id' => 'A suitable vehicle is required for this tour.']);
        }

        return $this->respond(fn (): array => $estimates->tour(
            $tour, Car::query()->findOrFail($data['car_id']), (int) $data['passengers'],
            CarbonImmutable::createFromFormat('Y-m-d', $data['booking_date'], config('app.timezone')),
            $data['promo_code'] ?? null, $data['customer_email'] ?? null,
        ));
    }

    public function transfer(TransferEstimateRequest $request, ServiceEstimateService $estimates): JsonResponse
    {
        $data = $request->validated();

        return $this->respond(fn (): array => $estimates->transfer(
            Car::query()->findOrFail($data['car_id']),
            $request->routePoints(),
            (int) $data['passengers'],
            (int) ($data['extra_waiting_minutes'] ?? 0),
            $data['promo_code'] ?? null,
            $data['customer_email'] ?? null,
        ));
    }

    public function privateDriver(
        PrivateDriverEstimateRequest $request,
        ServiceEstimateService $estimates,
    ): JsonResponse {
        $data = $request->validated();

        return $this->respond(fn (): array => $estimates->privateDriver(
            Car::query()->findOrFail($data['car_id']),
            (int) $data['duration_minutes'],
            (int) $data['passengers'],
            $data['promo_code'] ?? null,
            $data['customer_email'] ?? null,
        ));
    }

    public function customTrip(CustomTripEstimateRequest $request, ServiceEstimateService $estimates): JsonResponse
    {
        $data = $request->validated();

        return $this->respond(fn (): array => $estimates->customTrip(
            Car::query()->findOrFail($data['car_id']),
            $request->routePoints(),
            (int) $data['passengers'],
            $data['promo_code'] ?? null,
            $data['customer_email'] ?? null,
        ));
    }

    /** @param callable(): array<string, mixed> $estimate */
    private function respond(callable $estimate): JsonResponse
    {
        try {
            return response()->json(['data' => $estimate()]);
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['estimate' => $exception->getMessage()]);
        }
    }
}
