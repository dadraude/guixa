<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Member;

/**
 * A member can be reactivated whatever the state of their household (ADR 0011):
 * if the household is inactive they are treated as a member without a household
 * until it is reactivated. Commissions are not restored, they are assigned
 * again by hand.
 */
class ReactivateMember
{
    public function handle(Member $member): void
    {
        $member->update(['is_active' => true]);
    }
}
