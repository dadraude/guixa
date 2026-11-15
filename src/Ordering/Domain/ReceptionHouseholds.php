<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

/** What Ordering asks Membership for when a household can be given a share in a reception (R16). */
interface ReceptionHouseholds
{
    /** @return list<HouseholdInfo> the active households by name */
    public function active(): array;
}
