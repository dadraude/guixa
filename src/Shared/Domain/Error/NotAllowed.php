<?php

namespace Guixa\Shared\Domain\Error;

use DomainException;

final class NotAllowed extends DomainException
{
    public static function forRole(string $role): self
    {
        return new self("This action requires the {$role} role.");
    }

    public static function notYours(string $what): self
    {
        return new self("This {$what} is not yours to act on.");
    }
}
