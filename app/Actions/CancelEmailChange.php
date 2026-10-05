<?php

namespace App\Actions;

use App\Models\User;

/**
 * Drops a pending email change so its link stops working.
 */
class CancelEmailChange
{
    public function handle(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();
    }
}
