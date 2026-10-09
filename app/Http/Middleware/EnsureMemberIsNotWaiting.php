<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\WaitingForHousehold;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a member with no active household and no commission on the waiting screen:
 * they may only edit their own details (name, phone, email, password),
 * pick the theme, switch profile and sign out. Checked on every request, so
 * it takes effect as soon as the last active household or commission is taken away.
 */
class EnsureMemberIsNotWaiting
{
    private const array ALLOWED_ROUTES = [
        'waiting',
        'logout',
        'password.setup',
        'password.setup.store',
        'profile.*',
        'email-change.*',
        'security.*',
        'user-password.update',
        'appearance.edit',
        'active-profile.*',
        'settings',
    ];

    public function __construct(private readonly WaitingForHousehold $waitingForHousehold) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if (! $this->waitingForHousehold->isWaiting($user)) {
            return $next($request);
        }

        return to_route('waiting');
    }
}
