<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\CurrencyCode;
use App\Enums\DriverTripStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'booking_number', 'secure_token_hash', 'idempotency_key', 'request_fingerprint',
        'customer_id', 'tour_id', 'group_tour_departure_id', 'car_id', 'driver_id', 'promo_code_id',
        'service_type', 'booking_date', 'pickup_time', 'starts_at', 'planned_end_at',
        'check_in_token_hash', 'attendance_status', 'checked_in_passengers', 'last_checked_in_at',
        'pickup_address', 'pickup_latitude', 'pickup_longitude', 'dropoff_address',
        'dropoff_latitude', 'dropoff_longitude', 'passengers', 'customer_name',
        'customer_email', 'customer_phone', 'customer_whatsapp',
        'customer_nationality', 'customer_notes', 'subtotal_minor',
        'discount_minor', 'deposit_amount_minor', 'total_minor', 'currency',
        'payment_method', 'payment_status', 'booking_status', 'driver_trip_status',
        'price_breakdown', 'admin_notes',
    ];

    protected static function booted(): void
    {
        self::creating(static function (Booking $booking): void {
            $booking->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'booking_date' => 'date',
            'starts_at' => 'immutable_datetime',
            'planned_end_at' => 'immutable_datetime',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'dropoff_latitude' => 'decimal:7',
            'dropoff_longitude' => 'decimal:7',
            'passengers' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'deposit_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'currency' => CurrencyCode::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'booking_status' => BookingStatus::class,
            'attendance_status' => AttendanceStatus::class,
            'checked_in_passengers' => 'integer',
            'last_checked_in_at' => 'immutable_datetime',
            'driver_trip_status' => DriverTripStatus::class,
            'price_breakdown' => 'array',
        ];
    }

    public function scopeBlockingAvailability(Builder $query): Builder
    {
        return $query->whereIn('booking_status', [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Assigned->value,
            BookingStatus::DriverOnTheWay->value,
            BookingStatus::DriverArrived->value,
            BookingStatus::InProgress->value,
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function groupTourDeparture(): BelongsTo
    {
        return $this->belongsTo(GroupTourDeparture::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function tourDetail(): HasOne
    {
        return $this->hasOne(TourBookingDetail::class);
    }

    public function transferDetail(): HasOne
    {
        return $this->hasOne(TransferBookingDetail::class);
    }

    public function privateDriverDetail(): HasOne
    {
        return $this->hasOne(PrivateDriverBookingDetail::class);
    }

    public function customTripDetail(): HasOne
    {
        return $this->hasOne(CustomTripBookingDetail::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('created_at');
    }

    public function driverTripStatusHistory(): HasMany
    {
        return $this->hasMany(DriverTripStatusHistory::class)->orderBy('created_at');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(BookingCheckIn::class)->orderBy('checked_in_at');
    }
}
