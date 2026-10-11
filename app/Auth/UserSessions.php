<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Ends the sessions and "remember me" cookies a person has open, for any
 * session store. Every session remembers the user's session version at sign-in;
 * raising the version makes all older sessions stale, and rotating the remember
 * token invalidates the cookies. A reactivation therefore cannot bring them back.
 */
class UserSessions
{
    private const string SESSION_KEY = 'session_version';

    public function end(User $user): void
    {
        $user->forceFill([
            'session_version' => $user->session_version + 1,
            'remember_token' => Str::random(60),
        ])->saveQuietly();
    }

    /**
     * Marks a session opened now as valid for the user's current version.
     */
    public function stamp(Session $session, User $user): void
    {
        $session->put(self::SESSION_KEY, $user->session_version);
    }

    /**
     * Sessions that carry no stamp predate it and count as version zero.
     */
    public function isCurrent(Session $session, User $user): bool
    {
        return $session->get(self::SESSION_KEY, 0) === $user->session_version;
    }
}
