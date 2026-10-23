<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

/** What Ordering asks Membership about households (R13, R16, R19). */
interface Households
{
    /**
     * @param  list<string>  $householdIds
     * @return array<string, HouseholdInfo> by household id; unknown households are left out
     */
    public function find(array $householdIds): array;
}
