<?php

namespace Guixa\Ordering\Domain;

/** The read side of Sales that reception and closure need. */
interface WeeklyBasketView
{
    public function snapshot(string $cycleId): WeeklyBasketSnapshot;

    /**
     * Containers each household still has to return across all its baskets, not per cycle (R18a).
     *
     * @param  list<string>  $householdIds
     * @return array<string, int> balance by household id
     */
    public function containerBalances(array $householdIds): array;
}
