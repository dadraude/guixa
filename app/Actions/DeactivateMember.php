<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Member;

/**
 * A member can no longer sign in with any profile; nothing else changes: they
 * keep their household, commissions and roles (ADR 0011). Open sessions end on
 * their next request (see SignOutUsersWhoMayNotSignIn).
 */
class DeactivateMember
{
    public function handle(Member $member): void
    {
        $member->update(['is_active' => false]);
    }
}
