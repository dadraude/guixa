<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * Remembers that a session was just opened with a magic link, so that the person
 * can set a new password without the old one. The grant is short-lived: a
 * session left open must not be able to take over the account.
 */
class MagicLinkSignIn
{
    public const string SESSION_KEY = 'signed_in_with_magic_link_at';

    public const int WINDOW_MINUTES = 15;

    public static function mark(Session $session): void
    {
        $session->put(self::SESSION_KEY, now()->timestamp);
    }

    public static function isRecent(Session $session): bool
    {
        $signedInAt = $session->get(self::SESSION_KEY);

        if (! is_int($signedInAt)) {
            return false;
        }

        return $signedInAt > now()->subMinutes(self::WINDOW_MINUTES)->timestamp;
    }

    public static function clear(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
