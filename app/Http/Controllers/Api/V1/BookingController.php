<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CreateBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingAccessTokenService;
use App\Services\Booking\BookingCheckInTokenService;
use Illuminate\Http\JsonResponse;

final class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        CreateBookingAction $action,
        BookingCheckInTokenService $checkInTokens,
    ): JsonResponse {
        $result = $action->execute($request->toData());
        $resource = (new BookingResource($result->booking))->resolve($request);
        $publicUrl = rtrim((string) config('app.frontend_url'), '/')
            ."/booking/{$result->booking->booking_number}/{$result->secureToken}";

        return response()->json([
            'data' => [
                ...$resource,
                'secure_token' => $result->secureToken,
                'public_url' => $publicUrl,
                'qr_payload' => $checkInTokens->payload($result->booking),
            ],
        ], $result->created ? 201 : 200);
    }

    public function show(
        string $bookingNumber,
        string $token,
        BookingAccessTokenService $tokens,
        BookingCheckInTokenService $checkInTokens,
    ): JsonResponse {
        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->with([
                'car', 'driver', 'statusHistory', 'tourDetail', 'transferDetail',
                'privateDriverDetail', 'customTripDetail.stops',
            ])
            ->firstOrFail();

        abort_unless($tokens->verify($booking, $token), 404);

        return response()->json(['data' => [
            ...(new BookingResource($booking))->resolve(request()),
            'qr_payload' => $checkInTokens->payload($booking),
        ]]);
    }
}
