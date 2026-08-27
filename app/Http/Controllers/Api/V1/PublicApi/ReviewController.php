<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Services\Booking\BookingAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Review::query()->where('active', true)->where('verified', true)->latest()->limit(20)->get()]);
    }

    public function store(Request $request, BookingAccessTokenService $tokens): JsonResponse
    {
        $validated = $request->validate([
            'booking_number' => ['required', 'string'],
            'secure_token' => ['required', 'string'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'review' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $booking = Booking::query()->where('booking_number', $validated['booking_number'])->firstOrFail();
        abort_unless($tokens->verify($booking, $validated['secure_token']), 404);
        abort_unless($booking->booking_status === BookingStatus::Completed, 422, 'Only completed bookings can be reviewed.');

        $review = Review::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'customer_name' => $booking->customer_name,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'review' => $validated['review'],
            'verified' => true,
            'active' => false,
        ]);

        return response()->json(['data' => $review], 201);
    }
}
