<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

// IntendedUrlAccess (DRA-36) only checks ability-only gates; a `can:ability,model` on a page
// would bring back the 403 after the profile choice, so it must be handled there first.
test('no page route authorizes with model arguments, which IntendedUrlAccess cannot check', function () {
    $offenders = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => collect(Route::gatherRouteMiddleware($route))
            ->contains(fn ($middleware) => is_string($middleware)
                && str_starts_with($middleware, Authorize::class.':')
                && str_contains($middleware, ',')))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($offenders)->toBe([], 'GET routes with `can:` model arguments need support in App\Auth\IntendedUrlAccess');
});
