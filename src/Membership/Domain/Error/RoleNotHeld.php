<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;
use Guixa\Shared\Domain\Role;

final class RoleNotHeld extends DomainException
{
    public static function for(string $accountId, Role $role): self
    {
        return new self("Account {$accountId} does not hold the {$role->value} role.");
    }
}
