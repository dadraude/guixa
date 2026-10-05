<?php

declare(strict_types=1);

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

        // Sign-in already spent the intended URL on this very redirect, so keep it
        // through the choice screen for the choice to go back to.
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return to_route('active-profile.edit');
    }
}
