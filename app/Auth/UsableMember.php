<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Member;

/**
 * The single definition of a usable member profile: the member has a household
 * or is in a commission. Its negation is the "waiting" state, and it decides
 * whether an administrator is offered the member profile at all.
 */
class UsableMember
{
    public function __construct(private readonly MemberRoles $memberRoles) {}

    public function check(Member $member): bool
    {
        return $member->household_id !== null || $this->memberRoles->isInAnyCommission($member);
    }
}
