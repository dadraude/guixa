<?php

namespace App\Http\Middleware;

use Closure;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActor
{
    public function __construct(private readonly ActorResolver $actors) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->user()?->getAuthIdentifier();

        app()->forgetInstance(Actor::class);

        if ($accountId === null) {
            return $next($request);
        }

        app()->instance(Actor::class, $this->actors->forAccount((string) $accountId));

        return $next($request);
    }
}
