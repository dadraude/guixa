<?php

namespace Guixa\Ordering\Infrastructure\Sales;

use Guixa\Ordering\Application\SalesBaskets;
use Guixa\Ordering\Application\WeeklySourceData;
use Guixa\Ordering\Application\WeeklySourceLineData;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\WeeklyBasketEntry;
use Guixa\Ordering\Domain\WeeklyBasketSnapshot;
use Guixa\Sales\Application\AddLateWeeklyLines;
use Guixa\Sales\Application\CreateWeeklyBasket;
use Guixa\Sales\Application\ReadHouseholdContainerBalances;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Application\ReadPendingWeeklyCycles;
use Guixa\Sales\Application\ReadWeeklyBasketStates;
use Guixa\Sales\Application\SyncWeeklyBasket;
use Guixa\Sales\Application\WeeklyBaseData;
use Guixa\Sales\Application\WeeklyBaseLineData;
use Guixa\Sales\Application\WeeklyBasketStateData;

final class SalesModuleBaskets implements SalesBaskets
{
    public function __construct(
        private readonly CreateWeeklyBasket $create,
        private readonly SyncWeeklyBasket $sync,
        private readonly AddLateWeeklyLines $late,
        private readonly ReadWeeklyBasketStates $states,
        private readonly ReadPendingWeeklyCycles $pending,
        private readonly ReadPendingCollections $collections,
        private readonly ReadHouseholdContainerBalances $balances,
        private readonly Households $households,
    ) {}

    public function createWeeklyBase(WeeklySourceData $source): void
    {
        ($this->create)($this->baseOf($source));
    }

    public function syncWeeklyBase(WeeklySourceData $source): void
    {
        ($this->sync)($this->baseOf($source));
    }

    public function addLateArrival(WeeklySourceData $source, array $productIds): void
    {
        ($this->late)($this->baseOf($source), $productIds);
    }

    public function pendingCycleIds(string $householdId): array
    {
        return ($this->pending)($householdId);
    }

    public function pendingCollectionCounts(): array
    {
        $counts = [];
        foreach (($this->collections)() as $pending) {
            if (! $pending->weekly || $pending->referenceCycleId === null) {
                continue;
            }
            $counts[$pending->referenceCycleId] = ($counts[$pending->referenceCycleId] ?? 0) + 1;
        }

        return $counts;
    }

    public function snapshot(string $cycleId): WeeklyBasketSnapshot
    {
        $states = ($this->states)($cycleId);

        return new WeeklyBasketSnapshot(array_map(
            fn (WeeklyBasketStateData $basket) => new WeeklyBasketEntry($basket->basketId, $basket->householdId, $basket->state, $basket->late, $basket->effective),
            $states,
        ));
    }

    public function containerBalances(array $householdIds): array
    {
        return ($this->balances)($householdIds);
    }

    private function baseOf(WeeklySourceData $source): WeeklyBaseData
    {
        $household = $this->households->find([$source->householdId])[$source->householdId] ?? null;

        return new WeeklyBaseData($source->orderId, $source->cycleId, $source->householdId, $household->name ?? '', array_map(
            fn (WeeklySourceLineData $line) => new WeeklyBaseLineData($line->sourceLineId, $line->productId, $line->name, $line->unit,
                $line->priceCents, $line->variable, $line->containersPerUnit, $line->ordered, $line->allocated),
            $source->lines,
        ));
    }
}
