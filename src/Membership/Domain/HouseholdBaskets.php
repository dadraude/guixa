<?php

namespace Guixa\Membership\Domain;

use Guixa\Membership\Domain\ValueObject\OpenHouseholdBasket;

interface HouseholdBaskets
{
    /** @return list<OpenHouseholdBasket> baskets still to validate or to collect */
    public function openFor(string $householdId): array;
}
