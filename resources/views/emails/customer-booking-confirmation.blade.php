<!doctype html>
<html lang="en">
<body style="margin:0;background:#f5f1e8;color:#1f2a24;font-family:Arial,sans-serif;line-height:1.6">
<div style="max-width:620px;margin:0 auto;padding:32px 20px">
    <div style="background:#ffffff;border-radius:18px;padding:32px">
        <h1 style="margin:0 0 16px;color:#173f35;font-size:26px">Booking received</h1>
        <p>Hello {{ $booking->customer_name }},</p>
        <p>We received your Armenia travel booking and will confirm it shortly.</p>
        <p>
            <strong>Booking number:</strong> {{ $booking->booking_number }}<br>
            <strong>Passengers:</strong> {{ $booking->passengers }}<br>
            <strong>Pickup:</strong> {{ $booking->pickup_address }}<br>
            <strong>Starts:</strong> {{ $booking->starts_at->format('d M Y H:i') }}<br>
            <strong>Total:</strong> {{ $booking->currency->formatMinor($booking->total_minor) }} {{ $booking->currency->value }}
        </p>
        <div style="margin:28px 0;text-align:center">
            <p style="font-weight:bold;color:#173f35">Your check-in QR ticket</p>
            {!! $qrSvg !!}
            <p style="font-size:13px;color:#68736d">Show this QR code to staff when you arrive.</p>
        </div>
        <p style="text-align:center">
            <a href="{{ $publicUrl }}" style="display:inline-block;background:#173f35;color:#ffffff;border-radius:10px;padding:12px 20px;text-decoration:none">View your booking</a>
        </p>
        <p style="font-size:13px;color:#68736d">Keep this private link safe; it provides access to your booking details.</p>
    </div>
</div>
</body>
</html>
