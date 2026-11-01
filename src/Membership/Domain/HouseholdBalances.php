<?php

namespace Guixa\Membership\Domain;

interface HouseholdBalances
{
    public function balanceCents(string $householdId): int;
}
