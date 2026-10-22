<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class EmailAlreadyInUse extends DomainException
{
    public static function for(string $email): self
    {
        return new self("The email {$email} already belongs to an account.");
    }
}
