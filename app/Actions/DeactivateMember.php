<?php

namespace App\Actions;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * A member leaves the cooperative while their household stays: they leave
 * every commission and can no longer sign in. Open sessions end on their next
 * request (see SignOutUsersWhoMayNotSignIn). History stays untouched.
 */
class DeactivateMember
{
    public function handle(Member $member): void
    {
        // Atomic on its own: the members.deactivate route calls this directly, outside DeactivateHousehold's transaction.
        DB::transaction(function () use ($member): void {
            $member->update(['is_active' => false]);
            $member->commissions()->detach();
        });
    }
}
