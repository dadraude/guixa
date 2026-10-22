<?php

namespace App\Http\Middleware;

use Closure;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Usage: `role:manager` or, for any of several, `role:manager,producer`. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = app()->bound(Actor::class) ? app(Actor::class) : null;

        if ($actor === null || ! $actor->hasAnyRole(...$roles)) {
            throw NotAllowed::forRole(implode(' or ', $roles));
        }

        return $next($request);
    }
}
