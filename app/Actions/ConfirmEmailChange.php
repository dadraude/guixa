<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\SecretHasher;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function handle(string $token): EmailChangeOutcome
    {
        try {
            return DB::transaction(fn () => $this->apply($token));
        } catch (UniqueConstraintViolationException) {
            // Lost the race for the address after the availability check. The
            // transaction is rolled back, so the pending change is dropped here.
            $this->discardPendingChange($token);

            return EmailChangeOutcome::EmailTaken;
        }
    }

    private function apply(string $token): EmailChangeOutcome
    {
        $user = User::query()
            ->where('pending_email_token_hash', SecretHasher::hash($token))
            ->where('pending_email_expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            return EmailChangeOutcome::LinkUnusable;
        }

        // Someone else may have taken the address since it was requested.
        $taken = User::query()->whereKeyNot($user->id)->where('email', $user->pending_email)->exists();

        if ($taken) {
            $this->clearPendingChange($user);

            return EmailChangeOutcome::EmailTaken;
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
        ]);
        $this->clearPendingChange($user);

        return EmailChangeOutcome::Applied;
    }

    private function discardPendingChange(string $token): void
    {
        User::query()
            ->where('pending_email_token_hash', SecretHasher::hash($token))
            ->update([
                'pending_email' => null,
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
            ]);
    }

    /**
     * Saves the user together with the cleared pending change, so the dead link
     * does not linger.
     */
    private function clearPendingChange(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();
    }
}
