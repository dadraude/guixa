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
 * administrator-only abilities.
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
        return $this->mayTakeAway($actor) || ! $this->memberRoles->memberHas($member, Role::Membership);
    }

    /**
     * Deactivating a household deactivates its members, so it is held to the same rule.
     */
    public function mayDeactivateHousehold(User $actor, Household $household): bool
    {
        if ($this->mayTakeAway($actor)) {
            return true;
        }

        return $household->members()->get()->every(fn (Member $member) => ! $this->memberRoles->memberHas($member, Role::Membership));
    }

    private function mayTakeAway(User $actor): bool
    {
        return Gate::forUser($actor)->allows(self::ABILITY);
    }
}
