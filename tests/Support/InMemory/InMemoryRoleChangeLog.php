<?php

namespace Tests\Support\InMemory;

use DateTimeImmutable;
use Guixa\Membership\Domain\RoleChange;
use Guixa\Membership\Domain\RoleChangeLog;
use Guixa\Shared\Domain\Role;

final class InMemoryRoleChangeLog implements RoleChangeLog
{
    /** @var list<array{accountId: string, role: Role, change: RoleChange, actorId: string, at: DateTimeImmutable}> */
    public array $entries = [];

    public function record(string $accountId, Role $role, RoleChange $change, string $actorId, DateTimeImmutable $at): void
    {
        $this->entries[] = ['accountId' => $accountId, 'role' => $role, 'change' => $change, 'actorId' => $actorId, 'at' => $at];
    }
}
