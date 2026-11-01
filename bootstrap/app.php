<?php

use App\Http\DomainErrorMapper;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogOutDeactivatedAccount;
use App\Http\Middleware\ResolveActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            LogOutDeactivatedAccount::class,
            ResolveActor::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->map(AuthorizationException::class, fn (AuthorizationException $e) => new AccessDeniedHttpException(__('errors.NotAllowed'), $e));
        $exceptions->map(Throwable::class, DomainErrorMapper::map(...));
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ($request->expectsJson() || $request->is('api/*')) && $e->getMessage() === 'Unauthenticated.'
                ? response()->json(['message' => __('Unauthenticated.')], 401)
                : null;
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $titles = [401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 419 => 'Page Expired', 429 => 'Too Many Requests', 500 => 'Server Error', 503 => 'Service Unavailable'];
            $title = match (true) {
                $e instanceof ThrottleRequestsException && $e->getMessage() === 'Too Many Attempts.' => 'Too Many Requests',
                $e->getPrevious() instanceof TokenMismatchException && $e->getMessage() === 'CSRF token mismatch.' => 'Page Expired',
                $e->getPrevious() instanceof ModelNotFoundException => 'Not Found',
                $e->getMessage() === '' => $titles[$e->getStatusCode()] ?? null,
                default => null,
            };

            return $title === null ? null : response()->json(['message' => __($title)], $e->getStatusCode(), $e->getHeaders());
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || (! $request->expectsJson() && ! $request->is('api/*')) ||
                $e instanceof HttpExceptionInterface || $e instanceof AuthenticationException ||
                $e instanceof ValidationException || $e instanceof HttpResponseException) {
                return null;
            }

            return response()->json(['message' => __('Server Error')], 500);
        });
    })->create();
