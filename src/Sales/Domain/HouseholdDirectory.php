<?php

namespace Guixa\Sales\Domain;

use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;

interface HouseholdDirectory
{
    /** @return list<HouseholdSnapshot> */
    public function all(): array;
}
