<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in user who has no password yet to the set-password screen
 * until they choose one, so both ways of signing in always work.
 */
class EnsurePasswordIsSet
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->password === null && ! $request->routeIs('password.setup', 'password.setup.store', 'logout')) {
            return to_route('password.setup');
        }

        return $next($request);
    }
}
