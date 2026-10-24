<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class HouseholdNotFound extends DomainException
{
    public static function withId(string $householdId): self
    {
        return new self("Household {$householdId} does not exist.");
    }
}
