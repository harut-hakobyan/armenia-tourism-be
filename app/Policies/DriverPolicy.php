<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;

final class DriverPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->role === UserRole::Manager
            || ($user->role === UserRole::Driver && $user->driver?->is($driver));
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->role === UserRole::Manager;
    }
}
