<?php

namespace App\Auth;

use App\Models\Commission;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place that knows how roles reach members: through the commissions
 * they are in. Always read from the database, so removing a member from a
 * commission or a role from a commission takes effect on the next request.
 * Inactive members have no roles.
 */
class MemberRoles
{
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
     * @return list<Role>
     */
    public function of(Member $member): array
    {
        if (! $member->is_active) {
            return [];
        }

        return $this->forCommissions($member->commissions()->pluck('commissions.id')->all());
    }

    /**
     * The roles held by the given commissions together.
     *
     * @param  array<int, string>  $commissionIds
     * @return list<Role>
     */
    public function forCommissions(array $commissionIds): array
    {
        return $this->inDeclaredOrder(DB::table('commission_role')
            ->whereIn('commission_id', $commissionIds)
            ->distinct()
            ->pluck('role')
            ->all());
    }

    /**
     * The roles each commission holds, keyed by commission id, in one query.
     *
     * @return array<string, list<Role>>
     */
    public function byCommission(): array
    {
        return DB::table('commission_role')
            ->get(['commission_id', 'role'])
            ->groupBy('commission_id')
            ->map(fn ($rows) => $this->inDeclaredOrder($rows->pluck('role')->all()))
            ->all();
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

    /**
     * @param  array<int, string>  $values
     * @return list<Role>
     */
    private function inDeclaredOrder(array $values): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => in_array($role->value, $values, true),
        ));
    }
}
