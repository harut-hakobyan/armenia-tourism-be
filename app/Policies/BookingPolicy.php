<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

final class BookingPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function view(User $user, Booking $booking): bool
    {
        return match ($user->role) {
            UserRole::Manager => true,
            UserRole::Driver => $user->driver?->id === $booking->driver_id,
            UserRole::Customer => $user->customer?->id === $booking->customer_id,
            default => false,
        };
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function assign(User $user, Booking $booking): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function updateDriverStatus(User $user, Booking $booking): bool
    {
        return $user->role === UserRole::Driver
            && $user->driver?->id === $booking->driver_id;
    }
}
