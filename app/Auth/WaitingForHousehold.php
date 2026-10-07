<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;

/**
 * The single definition of "waiting": an active member acting as a member who
 * has no household and no commission. They can do nothing until the
 * membership commission assigns them a household, so the app only lets them
 * edit their own details and sign out.
 */
class WaitingForHousehold
{
    public function __construct(
        private readonly ActiveProfile $activeProfile,
        private readonly UsableMember $usableMember,
    ) {}

    public function isWaiting(User $user): bool
    {
        $member = $user->member;

        if ($member === null || ! $member->is_active) {
            return false;
        }

        if ($this->activeProfile->current($user) !== Profile::Member) {
            return false;
        }

        return ! $this->usableMember->check($member);
    }
}
