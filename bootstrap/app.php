<?php

use App\Http\DomainErrorResponse;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveActor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias(['role' => EnsureRole::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveActor::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->map(NotAllowed::class, fn (NotAllowed $e) => new AccessDeniedHttpException($e->getMessage(), $e));
        $exceptions->render(fn (Throwable $e, Request $request) => DomainErrorResponse::for($e, $request));
        $exceptions->dontReportWhen(fn (Throwable $e) => DomainErrorResponse::matches($e));
    })->create();
