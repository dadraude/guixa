<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class NotAStaffRole extends DomainException
{
    public static function named(string $role): self
    {
        return new self("Role {$role} is not a staff role; only staff roles are granted here.");
    }
}
