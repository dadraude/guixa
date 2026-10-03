<?php

namespace App\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues and consumes magic links: single-use sign-in tokens, valid for
 * 15 minutes, of which only the hash is stored.
 */
class MagicLinkBroker
{
    public const int MINUTES_VALID = 15;

    /**
     * Email the user a new magic link. Earlier unused links stop working.
     * The email is queued once the surrounding transaction commits.
     */
    public function send(User $user): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $token) {
            $user->magicLinks()->whereNull('used_at')->delete();

            $user->magicLinks()->create([
                'token_hash' => self::hash($token),
                'expires_at' => now()->addMinutes(self::MINUTES_VALID),
            ]);
        });

        Mail::to($user)->queue(new MagicLinkMail(route('magic-link.show', ['token' => $token])));
    }

    /**
     * Use up the link with this token and return its user, or null when the
     * link does not exist, has expired or has already been used.
     */
    public function consume(string $token): ?User
    {
        return DB::transaction(function () use ($token) {
            $link = MagicLink::query()
                ->usable()
                ->where('token_hash', self::hash($token))
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                return null;
            }

            $link->forceFill(['used_at' => now()])->save();

            return $link->user;
        });
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
