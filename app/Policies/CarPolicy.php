<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Car;
use App\Models\User;

final class CarPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function view(User $user, Car $car): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, Car $car): bool
    {
        return $user->role === UserRole::Manager;
    }
}
