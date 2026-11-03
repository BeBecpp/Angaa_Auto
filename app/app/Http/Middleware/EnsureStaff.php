<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Active staff with a password-based staff login. Inactive staff lose access on their next request.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isStaff() || ! $user->active) {
            if ($user !== null && $user->isStaff() && ! $user->active) {
                auth()->logout();
                $request->session()->invalidate();
            }

            abort(403);
        }

        return $next($request);
    }
}
