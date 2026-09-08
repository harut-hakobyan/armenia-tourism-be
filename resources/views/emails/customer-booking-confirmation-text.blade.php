Booking received

Hello {{ $booking->customer_name }},

We received your Armenia travel booking and will confirm it shortly.
Booking number: {{ $booking->booking_number }}
Passengers: {{ $booking->passengers }}
Pickup: {{ $booking->pickup_address }}
Starts: {{ $booking->starts_at->format('d M Y H:i') }}
Total: {{ $booking->currency->formatMinor($booking->total_minor) }} {{ $booking->currency->value }}

Your check-in QR payload: {{ $checkInPayload }}
View your booking: {{ $publicUrl }}

Keep this private link safe; it provides access to your booking details.
