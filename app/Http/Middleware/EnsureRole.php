<?php

namespace App\Http\Middleware;

use Closure;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Usage: `role:manager` or, for any of several, `role:manager,producer`. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = app()->bound(Actor::class) ? app(Actor::class) : null;

        Gate::forUser($actor)->authorize('role', [$roles]);

        return $next($request);
    }
}
