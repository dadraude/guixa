<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;

/** Internal query for Membership, which settles households its own manager-only use cases already authorised (R35a); it takes no actor. */
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
