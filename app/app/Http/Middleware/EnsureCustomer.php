<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal access: an authenticated, active customer identity only. Staff sessions are not portal sessions.
 */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('portal.login'));
        }

        if (! $user->isCustomer() || ! $user->active) {
            abort(403, 'Энэ хэсэг зөвхөн үйлчлүүлэгчдэд зориулагдсан.');
        }

        return $next($request);
    }
}
