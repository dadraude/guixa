<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class HouseholdNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("There is no household {$id}.");
    }
}
