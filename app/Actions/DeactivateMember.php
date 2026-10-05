<?php

namespace App\Actions;

use App\Models\Member;

/**
 * A member leaves the cooperative while their household stays: they leave
 * every commission and can no longer sign in. Open sessions end on their next
 * request (see SignOutUsersWhoMayNotSignIn). History stays untouched.
 */
class DeactivateMember
{
    public function handle(Member $member): void
    {
        $member->update(['is_active' => false]);
        $member->commissions()->detach();
    }
}
