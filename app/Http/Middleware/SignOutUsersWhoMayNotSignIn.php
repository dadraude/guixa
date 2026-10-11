<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\UserSessions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a signed-in user who may no longer sign in, such as an
 * administrator who has been deactivated, and of one whose sessions were
 * ended on purpose (see UserSessions). Checking on every request works for any
 * session store and for "remember me" cookies alike.
 */
class SignOutUsersWhoMayNotSignIn
{
    public function __construct(private readonly UserSessions $userSessions) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ($user->canSignIn() && $this->userSessions->isCurrent($request->session(), $user))) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
