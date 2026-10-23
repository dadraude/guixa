<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class ManagerAlreadyExists extends DomainException
{
    public static function create(): self
    {
        return new self('A manager already exists; the first manager can only be created once.');
    }
}
