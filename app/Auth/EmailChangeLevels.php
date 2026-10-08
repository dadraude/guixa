<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Member;
use App\Models\User;

/**
 * Who may change someone else's email. The email signs people in and is shared
 * with the administrator profile, so each level only reaches people below it:
 * the owner reaches everyone else, an administrator reaches members who are not
 * administrators, and the membership role reaches members who neither hold the
 * membership role nor are administrators. Any member holding the administrator
 * profile counts as an administrator, active or not (DRA-57). Changing one's
 * own email is never decided here: it goes through the confirmation link.
 *
 * A class and not a Gate because Gate::before would let any administrator
 * through, including against administrator targets.
 */
class EmailChangeLevels
{
    public function __construct(
        private readonly ActiveProfile $activeProfile,
        private readonly MemberRoles $memberRoles,
    ) {}

    public function canChange(User $actor, Member $target): bool
    {
        if ($actor->is($target->user)) {
            return false;
        }

        if ($this->activeProfile->isActingAsOwner($actor)) {
            return true;
        }

        if ($target->user->administrator !== null) {
            return false;
        }

        if ($this->activeProfile->isActingAsAdministrator($actor)) {
            return true;
        }

        return $this->memberRoles->userHas($actor, Role::Membership)
            && ! $this->memberRoles->memberHas($target, Role::Membership);
    }
}
