<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\WeeklyBasketView;

/** What Ordering needs from Sales: weekly bases created and synchronized from orders, late arrivals, and their state. */
interface SalesBaskets extends WeeklyBasketView
{
    /** Creates the order's base basket, or does nothing when it already exists. */
    public function createWeeklyBase(WeeklySourceData $source): void;

    /** Makes an unvalidated base follow the order's current allocations; creates the base when missing. */
    public function syncWeeklyBase(WeeklySourceData $source): void;

    /**
     * Adds products that arrived late to the household's weekly baskets: an unvalidated base follows the whole source, otherwise the
     * lines join its open late draft or start one. Validated baskets never change.
     *
     * @param  list<string>  $productIds
     */
    public function addLateArrival(WeeklySourceData $source, array $productIds): void;

    /** @return list<string> cycles where the household has a weekly base to validate or to collect */
    public function pendingCycleIds(string $householdId): array;

    /** @return array<string, int> weekly baskets pending collection by cycle id, whatever the cycle's state; none for cycles without any */
    public function pendingCollectionCounts(): array;
}
