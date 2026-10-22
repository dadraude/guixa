<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class InvalidHouseholdName extends DomainException
{
    public static function blank(): self
    {
        return new self('A household needs a name.');
    }
}
