<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\Member;

/**
 * The commissions a member is in, by name, each with the roles it holds,
 * shaped for the pages.
 */
class MemberCommissionList
{
    public function __construct(private readonly CommissionRoles $commissionRoles) {}

    /**
     * Inactive members are listed with no commissions: they cannot sign in to see them.
     *
     * @return list<array{id: string, name: string, roles: list<array{value: string, label: string}>}>
     */
    public function of(Member $member): array
    {
        if (! $member->is_active) {
            return [];
        }

        $rolesByCommission = $this->commissionRoles->byCommission();

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
