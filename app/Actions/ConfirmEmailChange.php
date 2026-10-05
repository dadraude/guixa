<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\SecretHasher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a pending email change when its link is still valid. The token is
 * cleared on use, so the link works only once.
 */
class ConfirmEmailChange
{
    /**
     * Whether the link could still be used, without using it up.
     */
    public function isPending(string $token): bool
    {
        return User::query()
            ->where('pending_email_token_hash', SecretHasher::hash($token))
            ->where('pending_email_expires_at', '>', now())
            ->exists();
    }

    /**
     * @return bool Whether the email was changed.
     */
    public function handle(string $token): bool
    {
        return DB::transaction(function () use ($token): bool {
            $user = User::query()
                ->where('pending_email_token_hash', SecretHasher::hash($token))
                ->where('pending_email_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return false;
            }

            // Someone else may have taken the address since it was requested.
            $taken = User::query()->whereKeyNot($user->id)->where('email', $user->pending_email)->exists();

            if ($taken) {
                return false;
            }

            $user->forceFill([
                'email' => $user->pending_email,
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
            ])->save();

            return true;
        });
    }
}
