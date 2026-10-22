<?php

namespace Guixa\Shared\Infrastructure;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;

final class DatabaseActorResolver implements ActorResolver
{
    public function forAccount(string $accountId): Actor
    {
        $account = DB::table('users')->where('id', $accountId)->first(['household_id', 'producer_id']);

        $roles = DB::table('account_roles')
            ->where('user_id', $accountId)
            ->orderBy('role')
            ->pluck('role')
            ->map(fn (string $role) => Role::from($role))
            ->all();

        return new Actor(
            accountId: $accountId,
            roles: $roles,
            householdId: $account?->household_id,
            producerId: $account?->producer_id,
        );
    }
}
