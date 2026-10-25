<?php

namespace Guixa\Membership\Domain;

use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;

interface HouseholdPickups
{
    /** @return list<PendingHouseholdPickup> */
    public function pendingFor(string $householdId): array;
}
