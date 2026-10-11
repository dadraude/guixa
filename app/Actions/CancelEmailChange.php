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
        $clearedColumns = [
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ];

        // One conditional UPDATE decides and clears atomically, so a confirmation
        // committed meanwhile (e.g. from another device) is never reported as cancelled.
        $wasPending = User::query()->whereKey($user->id)->withPendingEmailChange()->update($clearedColumns) > 0;

        // Whatever is left is an expired leftover: drop it too.
        User::query()->whereKey($user->id)->update($clearedColumns);
        $user->forceFill($clearedColumns)->syncOriginal();

        return $wasPending;
    }
}
