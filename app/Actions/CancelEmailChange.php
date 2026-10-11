<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/**
 * Drops a pending email change so its link stops working.
 */
class CancelEmailChange
{
    /**
     * @return bool Whether a change was still pending; an expired one counts as none,
     *              like the profile page, which no longer shows it.
     */
    public function handle(User $user): bool
    {
        $wasPending = $user->pending_email_expires_at?->isFuture() === true && $user->pending_email !== null;

        $user->forceFill([
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();

        return $wasPending;
    }
}
