<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\Member;
use App\Models\User;

/**
 * The only place that knows how roles reach members: through the commissions
 * they are in. Inactive members have no roles.
 *
 * Registered as a scoped singleton: roles are read from the database once per
 * member and remembered for the life of one request (the gate and the shared
 * props ask for the same member); the next request starts empty, so removing a
 * member from a commission or a role from a commission takes effect then.
 *
 * Warning: writing roles or commission membership and then checking a gate in
 * the same request would see the remembered, stale roles. Such code must
 * invalidate the memo first (e.g. app()->forgetScopedInstances()).
 */
class MemberRoles
{
    /** @var array<string, list<Role>> */
    private array $rolesByMember = [];

    public function __construct(
        private readonly CommissionMemberships $memberships,
        private readonly CommissionRoles $commissionRoles,
    ) {}

    /**
     * Deliberately ignores the acting profile: administrators acting as
     * administrators are already let through by Gate::before, so reaching this
     * point means the person acts as a member and only their commissions count.
     */
    public function userHas(User $user, Role $role): bool
    {
        $member = $user->member;

        return $member !== null && $this->memberHas($member, $role);
    }

    public function memberHas(Member $member, Role $role): bool
    {
        return in_array($role, $this->of($member), true);
    }

    /**
     * Whether the member takes part in at least one commission. Inactive
     * members are in none.
     */
    public function isInAnyCommission(Member $member): bool
    {
        return Member::query()->whereKey($member->getKey())->activeInCommission()->exists();
    }

    /**
     * @return list<Role>
     */
    public function of(Member $member): array
    {
        if (! $member->is_active) {
            return [];
        }

        return $this->rolesByMember[$member->id] ??= $this->forCommissions($this->memberships->commissionIdsOf($member));
    }

    /**
     * Works out the roles of a whole page of members with two queries instead
     * of two per member, so later of() calls read the memo. Entries already
     * remembered are kept, never overwritten; inactive members have no roles
     * and need no query.
     *
     * @param  iterable<Member>  $members
     */
    public function preload(iterable $members): void
    {
        $pendingIds = [];

        foreach ($members as $member) {
            if ($member->is_active && ! isset($this->rolesByMember[$member->id])) {
                $pendingIds[] = (string) $member->id;
            }
        }

        if ($pendingIds === []) {
            return;
        }

        $rolesByMember = $this->commissionRoles->forEachGroup($this->memberships->commissionIdsByMember($pendingIds));

        foreach ($pendingIds as $memberId) {
            $this->rolesByMember[$memberId] = $rolesByMember[$memberId] ?? [];
        }
    }

    /**
     * The roles held by the given commissions together.
     *
     * @param  array<int, string>  $commissionIds
     * @return list<Role>
     */
    public function forCommissions(array $commissionIds): array
    {
        return $this->commissionRoles->forCommissions($commissionIds);
    }

    /** @return array<string, list<Role>> the roles each commission holds, in one query */
    public function byCommission(): array
    {
        return $this->commissionRoles->byCommission();
    }

    /**
     * The commissions the member is in, by name, each with the roles it holds,
     * shaped for the pages. Inactive members are in none.
     *
     * @return list<array{id: string, name: string, roles: list<array{value: string, label: string}>}>
     */
    public function commissionsOf(Member $member): array
    {
        if (! $member->is_active) {
            return [];
        }

        $rolesByCommission = $this->byCommission();

        return array_values($member->commissions()->orderBy('name')->get()
            ->map(fn (Commission $commission) => [
                'id' => $commission->id,
                'name' => $commission->name,
                'roles' => array_map(
                    fn (Role $role) => $role->toOption(),
                    $rolesByCommission[$commission->id] ?? [],
                ),
            ])
            ->all());
    }
}
