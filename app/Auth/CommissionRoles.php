<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Reads the commission_role pivot and normalises what it finds: roles always
 * come out unique and in the order Role declares them. Used only by
 * MemberRoles, so the rest of the code never depends on how roles are stored.
 */
class CommissionRoles
{
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
     * The roles of each group of commissions together, in one query. Groups
     * come out as forCommissions() would give them, one by one.
     *
     * @param  array<string, list<string>>  $commissionIdsByGroup
     * @return array<string, list<Role>>
     */
    public function forEachGroup(array $commissionIdsByGroup): array
    {
        $rolesByCommission = $this->byCommission();

        return array_map(fn (array $commissionIds) => $this->inDeclaredOrder(array_map(
            fn (Role $role) => $role->value,
            array_merge(...array_map(fn (string $id) => $rolesByCommission[$id] ?? [], $commissionIds)),
        )), $commissionIdsByGroup);
    }

    /**
     * @param  array<mixed>  $values  role values, duplicates allowed
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
