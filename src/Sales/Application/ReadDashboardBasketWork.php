<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\CyclePickupDates;

/** Internal query for the dashboard, which authorises its own scopes; it takes no actor. Eligibility is the one pending-collection rule: no wallet balance, cycle state or household activity filter. */
final class ReadDashboardBasketWork
{
    public function __construct(
        private readonly ReadPendingCollections $pending,
        private readonly CyclePickupDates $pickupDates,
    ) {}

    /** @return list<DashboardBasketWorkData> one household's pending collections when given, otherwise every household's */
    public function __invoke(?string $householdId = null): array
    {
        $pending = ($this->pending)($householdId);
        $dates = $this->pickupDates->forCycles(array_values(array_unique(array_filter(array_map(fn (PendingCollectionData $item) => $item->cycleId, $pending)))));

        return array_map(
            fn (PendingCollectionData $item) => new DashboardBasketWorkData($item->basketId, $item->cycleId, $item->cycleId === null ? null : ($dates[$item->cycleId] ?? null)),
            $pending,
        );
    }
}
