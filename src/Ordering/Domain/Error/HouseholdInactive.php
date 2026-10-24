<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class HouseholdInactive extends DomainException
{
    public static function withId(string $householdId): self
    {
        return new self("Household {$householdId} is deactivated, so it cannot be allocated anything.");
    }
}
