<?php

namespace App\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues and consumes magic links: single-use sign-in tokens, valid for
 * 15 minutes, of which the database stores only the hash. A link asked for from a
 * browser session also carries a 6-digit code that is accepted in that
 * session alone. Using either the link or the code uses up both.
 */
class MagicLinkBroker
{
    public const int MINUTES_VALID = 15;

    private const string SESSION_KEY = 'magic_link.id';

    /** The email the session last asked a link for; sign-in throttling counts against it. */
    public const string EMAIL_SESSION_KEY = 'magic_link.email';

    /**
     * Email the user a new magic link, with a code bound to the given session
     * if there is one. Earlier unused links stop working. The email is queued
     * once the surrounding transaction commits.
     */
    public function send(User $user, ?Session $session = null): void
    {
        $token = Str::random(64);
        $code = $session === null ? null : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $link = DB::transaction(function () use ($user, $token, $code) {
            // Lock the user so that concurrent requests issue links one at a
            // time and only the newest stays usable.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $user->magicLinks()->whereNull('used_at')->delete();

            return $user->magicLinks()->create([
                'token_hash' => self::hash($token),
                'code_hash' => $code === null ? null : self::hash($code),
                'expires_at' => now()->addMinutes(self::MINUTES_VALID),
            ]);
        });

        $session?->put(self::SESSION_KEY, $link->id);

        Mail::to($user)->queue(new MagicLinkMail(route('magic-link.show', ['token' => $token]), $code));
    }

    /**
     * Forget any code the session was waiting for, so that a request for an
     * unknown email leaves nothing usable behind.
     */
    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    /**
     * Use up the link with this token and return its user, or null when the
     * link does not exist, has expired or has already been used.
     */
    public function consume(string $token): ?User
    {
        return $this->useUp(MagicLink::query()->where('token_hash', self::hash($token)));
    }

    /**
     * Use up the link this session asked for if the code matches, and return
     * its user; null otherwise.
     */
    public function consumeCode(Session $session, string $code): ?User
    {
        $id = $session->get(self::SESSION_KEY);

        if (! is_string($id)) {
            return null;
        }

        $user = $this->useUp(MagicLink::query()->whereKey($id)->where('code_hash', self::hash($code)));

        if ($user !== null) {
            $this->forget($session);
        }

        return $user;
    }

    /**
     * @param  Builder<MagicLink>  $query
     */
    private function useUp(Builder $query): ?User
    {
        return DB::transaction(function () use ($query) {
            $link = $query->usable()->lockForUpdate()->first();

            if ($link === null) {
                return null;
            }

            $link->forceFill(['used_at' => now()])->save();

            return $link->user;
        });
    }

    private static function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }
}
