<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Only administrators take the membership role away from a member. Otherwise
 * a member with the role could strip it from a colleague, change their email
 * (see EmailChangeLevels) and sign in as them. The ability is defined as false
 * for everyone and administrators pass through Gate::before, as with the other
 * administrator-only abilities. Nobody, administrators included, deactivates
 * the owner.
 */
class MembershipRoleProtection
{
    public const string ABILITY = 'take-membership-role-away';

    public function __construct(
        private readonly MemberRoles $memberRoles,
        private readonly MembershipRoleRetention $retention,
    ) {}

    public function mayRemoveFromCommission(User $actor, Commission $commission): bool
    {
        return $this->mayRemoveFromCommissionHolding($actor, $this->memberRoles->forCommissions([$commission->id]));
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

    /**
     * Removing a member from a commission holding the membership role is also allowed to the
     * membership role when the member keeps the role through another commission (DRA-67).
     * Reads the current data, so callers that lock the member get a decision on what is stored.
     */
    public function mayRemoveMemberFromCommission(User $actor, Commission $commission, Member $member): bool
    {
        return $this->mayRemoveMemberHolding(
            $actor,
            $this->memberRoles->forCommissions([$commission->id]),
            $this->retention->keptOutside($commission, $member),
        );
    }

    /**
     * The one rule the server and the commissions page share, for pages that already know
     * the roles of every commission and whether the member keeps the role elsewhere.
     *
     * @param  list<Role>  $commissionRoles
     */
    public function mayRemoveMemberHolding(User $actor, array $commissionRoles, bool $memberKeepsMembershipRole): bool
    {
        return $this->mayRemoveFromCommissionHolding($actor, $commissionRoles) || $memberKeepsMembershipRole;
    }

    public function mayDeactivateMember(User $actor, Member $member): bool
    {
        if ($this->belongsToOwner($member)) {
            return false;
        }

        return $this->mayTakeAway($actor) || $this->mayBeDeactivatedByMembershipRole($member);
    }

    /**
     * Deactivating a household together with its members skips whoever the actor may not
     * deactivate on their own, and the actor themselves, so they cannot lock themselves out.
     * Callers go through mayChangeAlongWithHousehold.
     */
    public function mayDeactivateAlongWithHousehold(User $actor, Member $member): bool
    {
        return $member->user_id !== $actor->id && $this->mayDeactivateMember($actor, $member);
    }

    /**
     * Whether a status change on a household reaches this member, who has the active flag the
     * household has now: deactivating skips whoever the actor may not deactivate, reactivating
     * whoever the actor may not reactivate. The one rule the actions and the dialog share.
     */
    public function mayChangeAlongWithHousehold(User $actor, Member $member, bool $householdIsActive): bool
    {
        return $householdIsActive
            ? $this->mayDeactivateAlongWithHousehold($actor, $member)
            : $this->mayReactivateMember($actor, $member);
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
     * membership role, active or not (DRA-34) nor when they are an active administrator, since that
     * would stop them acting as administrator. An inactive member who keeps the
     * administrator profile does not block it (DRA-57, from DRA-72).
     */
    private function mayBeDeactivatedByMembershipRole(Member $member): bool
    {
        return ! $this->memberRoles->memberHolds($member, Role::Membership)
            && ! $member->isActiveAdministrator();
    }

    /**
     * Reactivating someone who holds the membership role or the administrator
     * profile is reserved to administrators, as deactivating them is. The
     * target is inactive, so the profile counts whether or not it is active.
     */
    public function mayReactivateMember(User $actor, Member $member): bool
    {
        if ($this->mayTakeAway($actor)) {
            return true;
        }

        return ! $this->memberRoles->memberHolds($member, Role::Membership)
            && $member->user->administrator === null;
    }

    private function mayTakeAway(User $actor): bool
    {
        return Gate::forUser($actor)->allows(self::ABILITY);
    }
}
