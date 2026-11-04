<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;

/** Read model for the Dashboard: the pickups where a household still has a weekly base to validate or to collect (R35a); it takes no actor, the dashboard authorises the household. */
final class ReadPendingPickups
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly SalesBaskets $baskets,
    ) {}

    /** @return list<PendingPickupData> ordered by pickup date; a cycle appears while its household base is still to validate or to collect */
    public function __invoke(string $householdId): array
    {
        $pending = [];

        foreach ($this->baskets->pendingCycleIds($householdId) as $cycleId) {
            $cycle = $this->cycles->get($cycleId);

            if ($cycle !== null) {
                $pending[] = new PendingPickupData($cycleId, $cycle->pickupDate()->format('Y-m-d'));
            }
        }

        usort($pending, fn (PendingPickupData $a, PendingPickupData $b) => [$a->pickupDate, $a->cycleId] <=> [$b->pickupDate, $b->cycleId]);

        return $pending;
    }
}
