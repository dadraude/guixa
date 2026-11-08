<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Role;

/** Append-only record of who changed which staff role and when; nothing reads it yet (R2a). */
interface RoleChangeLog
{
    public function record(string $accountId, Role $role, RoleChange $change, string $actorId, DateTimeImmutable $at): void;
}
