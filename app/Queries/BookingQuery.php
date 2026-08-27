<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

final class BookingQuery
{
    /** @param array<string, mixed> $filters */
    public function build(array $filters): Builder
    {
        return Booking::query()
            ->with(['tour.translations', 'car', 'driver'])
            ->when($filters['booking_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('booking_status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('payment_status', $status))
            ->when($filters['service_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('service_type', $type))
            ->when($filters['car_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('car_id', $id))
            ->when($filters['driver_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('driver_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('booking_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('booking_date', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): Builder {
                $term = '%'.trim($search).'%';

                return $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('booking_number', 'like', $term)
                        ->orWhere('customer_name', 'like', $term)
                        ->orWhere('customer_email', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhere('pickup_address', 'like', $term);
                });
            })
            ->orderBy('starts_at', ($filters['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
    }
}
