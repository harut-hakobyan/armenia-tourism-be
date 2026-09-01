<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CheckInBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\LookupBookingCheckInRequest;
use App\Http\Requests\CheckIn\StoreBookingCheckInRequest;
use App\Http\Resources\CheckInBookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingCheckInTokenService;
use Illuminate\Support\Facades\Gate;

final class CheckInController extends Controller
{
    public function lookup(
        LookupBookingCheckInRequest $request,
        BookingCheckInTokenService $tokens,
    ): CheckInBookingResource {
        $booking = $this->booking((string) $request->validated('token'), $tokens);
        Gate::authorize('checkIn', $booking);

        return new CheckInBookingResource($this->load($booking));
    }

    public function store(
        StoreBookingCheckInRequest $request,
        BookingCheckInTokenService $tokens,
        CheckInBookingAction $action,
    ): CheckInBookingResource {
        $validated = $request->validated();
        $booking = $this->booking((string) $validated['token'], $tokens);
        Gate::authorize('checkIn', $booking);
        $booking = $action->execute(
            $booking,
            $request->user(),
            isset($validated['passengers']) ? (int) $validated['passengers'] : null,
            $validated['notes'] ?? null,
            $request->ip(),
        );

        return new CheckInBookingResource($booking);
    }

    private function booking(string $token, BookingCheckInTokenService $tokens): Booking
    {
        return $tokens->findBooking($token);
    }

    private function load(Booking $booking): Booking
    {
        return $booking->load(['tour.translations', 'car', 'driver', 'checkIns.checkedInBy']);
    }
}
