<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class InvalidEmail extends DomainException
{
    public static function for(string $raw): self
    {
        return new self("'{$raw}' is not a valid email address.");
    }
}
