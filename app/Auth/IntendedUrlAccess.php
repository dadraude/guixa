<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Whether the user, acting as their current profile, passes the `can:` gates
 * of the route behind a URL. It reads the route's own middleware, so the
 * authorization rules stay declared in one place (the routes and the gates).
 */
class IntendedUrlAccess
{
    // The router resolves the `can:` alias to the Authorize class before we see it.
    private const string CAN_PREFIX = Authorize::class.':';

    public function __construct(private readonly Router $router) {}

    public function allows(User $user, string $url): bool
    {
        $request = Request::create($url);

        try {
            $route = $this->router->getRoutes()->match($request);
        } catch (NotFoundHttpException) {
            // Nothing to protect; the URL answers for itself when visited.
            return true;
        }

        foreach ($this->router->gatherRouteMiddleware($route) as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, self::CAN_PREFIX)) {
                continue;
            }

            // Only ability-only gates are checked: those with model arguments
            // need route binding, and they still guard the page when it is visited.
            $ability = substr($middleware, strlen(self::CAN_PREFIX));

            if (str_contains($ability, ',')) {
                continue;
            }

            if (Gate::forUser($user)->denies($ability)) {
                return false;
            }
        }

        return true;
    }
}
