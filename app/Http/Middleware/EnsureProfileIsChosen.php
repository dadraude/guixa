<?php

namespace App\Http\Middleware;

use App\Auth\ActiveProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a person who is both a member and an administrator to the choice
 * screen until they pick the profile to act as.
 */
class EnsureProfileIsChosen
{
    public function __construct(private readonly ActiveProfile $activeProfile) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs('active-profile.*', 'password.setup', 'password.setup.store', 'logout')) {
            return $next($request);
        }

        if (! $this->activeProfile->isChoicePending($user)) {
            return $next($request);
        }

        return to_route('active-profile.edit');
    }
}
