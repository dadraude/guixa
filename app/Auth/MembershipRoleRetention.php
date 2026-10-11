<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\Member;

/**
 * Whether a member would still hold the membership role after leaving one commission
 * (DRA-67). Inactive members count, as for the other protections (DRA-87).
 */
class MembershipRoleRetention
{
    public function __construct(private readonly CommissionRoles $commissionRoles) {}

    /**
     * Reads the current data, never the per-request memo, so a caller that locked
     * the member decides on what is stored.
     */
    public function keptOutside(Commission $commission, Member $member): bool
    {
        return $this->keptOutsideOf(
            (string) $commission->id,
            $member->commissions()->get(),
            $this->commissionRoles->byCommission(),
        );
    }

    /**
     * For pages that already know the commissions of the member and the roles of every one.
     *
     * @param  iterable<Commission>  $memberCommissions
     * @param  array<string, list<Role>>  $rolesByCommission
     */
    public function keptOutsideOf(string $commissionId, iterable $memberCommissions, array $rolesByCommission): bool
    {
        foreach ($memberCommissions as $other) {
            $otherId = (string) $other->id;
            if ($otherId !== $commissionId && in_array(Role::Membership, $rolesByCommission[$otherId] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
