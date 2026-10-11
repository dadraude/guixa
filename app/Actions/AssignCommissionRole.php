<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\Role;
use App\Models\Commission;
use Illuminate\Support\Facades\DB;

/**
 * Gives a commission a role under the lock on its row that DeleteCommission takes, so a role
 * assigned during a deletion finds the commission gone instead of failing on the foreign key.
 * Assigning a role it already holds changes nothing.
 */
class AssignCommissionRole
{
    public function handle(Commission $commission, Role $role): void
    {
        DB::transaction(function () use ($commission, $role): void {
            Commission::query()->whereKey($commission->getKey())->lockForUpdate()->firstOrFail()->assignRole($role);
        });
    }
}
