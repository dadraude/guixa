<?php

namespace App\Auth;

/**
 * Hashes the secrets we email (link tokens, codes) so that only their hash is
 * stored; keyed with the app key, so a leaked table is not enough to use them.
 */
class SecretHasher
{
    public static function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }
}
