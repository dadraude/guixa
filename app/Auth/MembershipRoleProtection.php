<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Only administrators take the membership role away from a member. Otherwise
 * a member with the role could strip it from a colleague, change their email
 * (see EmailChangeLevels) and sign in as them. The ability is defined as false
 * for everyone and administrators pass through Gate::before, as with the other
 * administrator-only abilities. Nobody, administrators included, deactivates
 * the owner or a household the owner belongs to.
 */
class MembershipRoleProtection
{
    public const string ABILITY = 'take-membership-role-away';

    public function __construct(private readonly MemberRoles $memberRoles) {}

    public function mayRemoveFromCommission(User $actor, Commission $commission): bool
    {
        return $this->mayRemoveFromCommissionHolding($actor, $commission->roles());
    }

    /**
     * For pages that already know the roles of every commission and must not ask again.
     *
     * @param  list<Role>  $commissionRoles
     */
    public function mayRemoveFromCommissionHolding(User $actor, array $commissionRoles): bool
    {
        return $this->mayTakeAway($actor) || ! in_array(Role::Membership, $commissionRoles, true);
    }

    public function mayDeleteCommission(User $actor, Commission $commission): bool
    {
        return $this->mayRemoveFromCommission($actor, $commission);
    }

    public function mayDeactivateMember(User $actor, Member $member): bool
    {
        if ($this->belongsToOwner($member)) {
            return false;
        }

        return $this->mayTakeAway($actor) || $this->mayBeDeactivatedByMembershipRole($member);
    }

    /**
     * Deactivating a household deactivates its members, so it is held to the same rule.
     */
    public function mayDeactivateHousehold(User $actor, Household $household): bool
    {
        $members = $household->loadMissing('members.user.administrator')->members;

        if ($members->contains(fn (Member $member) => $this->belongsToOwner($member))) {
            return false;
        }

        if ($this->mayTakeAway($actor)) {
            return true;
        }

        return $members->every(fn (Member $member) => $this->mayBeDeactivatedByMembershipRole($member));
    }

    /**
     * The owner can never be deactivated, by anyone: it would lock them out.
     * Checked here and not through the Gate so Gate::before cannot let administrators past it.
     */
    private function belongsToOwner(Member $member): bool
    {
        return $member->user->administrator?->is_owner === true;
    }

    /**
     * Whether the membership role may deactivate this member: not when they hold the
     * membership role (DRA-34) nor when they are an administrator, since that would
     * stop them acting as administrator.
     */
    private function mayBeDeactivatedByMembershipRole(Member $member): bool
    {
        return ! $this->memberRoles->memberHas($member, Role::Membership)
            && $member->user->administrator === null;
    }

    private function mayTakeAway(User $actor): bool
    {
        return Gate::forUser($actor)->allows(self::ABILITY);
    }
}
