<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(
            static fn (string $role): UserRole => UserRole::from($role),
            $roles,
        );

        if (! $request->user()?->is_active || ! $request->user()->hasAnyRole(...$allowedRoles)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to access this resource.');
        }

        return $next($request);
    }
}
