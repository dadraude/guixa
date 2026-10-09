<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Member;

/**
 * The single definition of a usable member profile: the member has an active
 * household or is in a commission. Its negation is the "waiting" state, and it decides
 * whether an administrator is offered the member profile at all.
 *
 * Registered as a scoped singleton: every administrator is also a member, so
 * the gates ask this for the same member many times per request (once per
 * household on the households page); the answer is remembered for the life of
 * one request, like MemberRoles.
 */
class UsableMember
{
    /** @var array<string, bool> */
    private array $usableByMember = [];

    public function __construct(private readonly MemberRoles $memberRoles) {}

    public function check(Member $member): bool
    {
        if ($member->hasActiveHousehold()) {
            return true;
        }

        return $this->usableByMember[$member->id] ??= $this->memberRoles->isInAnyCommission($member);
    }
}
