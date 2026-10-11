<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auth\UserSessions;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Every sign-in, including the one a remember cookie triggers, stamps its
 * session with the user's session version (see UserSessions).
 */
class StampSessionOnLogin
{
    public function __construct(private readonly UserSessions $userSessions) {}

    public function handle(Login $event): void
    {
        $guard = Auth::guard($event->guard);

        if (! $guard instanceof SessionGuard || ! $event->user instanceof User) {
            return;
        }

        $this->userSessions->stamp($guard->getSession(), $event->user);
    }
}
