<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupNotFound extends DomainException
{
    public static function forHousehold(string $cycleId, string $householdId): self
    {
        return new self("Household {$householdId} has no pickup in cycle {$cycleId}.");
    }
}
