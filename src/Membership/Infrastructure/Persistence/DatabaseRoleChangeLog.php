<?php

namespace Guixa\Membership\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Membership\Domain\RoleChange;
use Guixa\Membership\Domain\RoleChangeLog;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseRoleChangeLog implements RoleChangeLog
{
    public function record(string $accountId, Role $role, RoleChange $change, string $actorId, DateTimeImmutable $at): void
    {
        DB::table('role_changes')->insert([
            'id' => (string) Str::uuid7(),
            'user_id' => $accountId,
            'role' => $role->value,
            'change' => $change->value,
            'actor_id' => $actorId,
            'occurred_at' => $at->format(DATE_ATOM),
        ]);
    }
}
