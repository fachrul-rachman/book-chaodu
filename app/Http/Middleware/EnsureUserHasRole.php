<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        $expectedRole = UserRole::tryFrom($role);

        if (! $expectedRole || $user->role !== $expectedRole) {
            abort(403);
        }

        return $next($request);
    }
}
