<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\CyclePickupDates;

/** Internal query for the dashboard, which authorises its own scopes; it takes no actor. Collection is the one pending-collection rule: no wallet balance, cycle state or household activity filter. Drafts follow the basket's own contents, never its amount. */
final class ReadDashboardBasketWork
{
    public function __construct(
        private readonly ReadPendingCollections $pending,
        private readonly CyclePickupDates $pickupDates,
        private readonly BasketRepository $baskets,
        private readonly WeeklySourceOrders $sourceOrders,
    ) {}

    /** @return list<DashboardBasketWorkData> the collection scope: one household's pending collections when given, otherwise every household's; the drafts scope: every household's draft that staff can prepare or validate */
    public function __invoke(?string $householdId = null, DashboardBasketScope $scope = DashboardBasketScope::Collection): array
    {
        return match ($scope) {
            DashboardBasketScope::Collection => $this->collection($householdId),
            DashboardBasketScope::Drafts => $this->drafts(),
        };
    }

    /** @return list<DashboardBasketWorkData> */
    private function collection(?string $householdId): array
    {
        $pending = ($this->pending)($householdId);
        $dates = $this->datesOf(array_map(fn (PendingCollectionData $item) => $item->cycleId, $pending));

        return array_map(
            fn (PendingCollectionData $item) => new DashboardBasketWorkData($item->basketId, $item->cycleId, $this->dateOf($item->cycleId, $dates), DashboardBasketStage::Collect),
            $pending,
        );
    }

    /** @return list<DashboardBasketWorkData> */
    private function drafts(): array
    {
        $drafts = array_values(array_filter($this->baskets->drafts(), fn (Basket $basket) => $basket->canBeValidated()));
        $cycles = $this->sourceOrders->cyclesOf(array_values(array_filter(array_map(
            fn (Basket $basket) => $basket->isWeekly() ? $basket->sourceOrderId : null,
            $drafts,
        ))));
        $dates = $this->datesOf(array_values($cycles));

        // Every draft that validation accepts is validation work; Prepare stays in the contract but no state reaches it today (001 R19a/R22).
        return array_map(function (Basket $basket) use ($cycles, $dates) {
            $cycleId = $basket->isWeekly() ? ($cycles[$basket->sourceOrderId] ?? null) : null;

            return new DashboardBasketWorkData($basket->id, $cycleId, $this->dateOf($cycleId, $dates), DashboardBasketStage::Validate);
        }, $drafts);
    }

    /**
     * @param  list<?string>  $cycleIds
     * @return array<string, string>
     */
    private function datesOf(array $cycleIds): array
    {
        return $this->pickupDates->forCycles(array_values(array_unique(array_filter($cycleIds))));
    }

    /** @param  array<string, string>  $dates */
    private function dateOf(?string $cycleId, array $dates): ?string
    {
        return $cycleId === null ? null : ($dates[$cycleId] ?? null);
    }
}
