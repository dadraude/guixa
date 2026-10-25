<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;

final class ListPendingPickups
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
    ) {}

    /** @return list<PendingPickupData> ordered by pickup date */
    public function __invoke(Actor $actor, string $householdId): array
    {
        $actor->assertManager();
        $pending = [];

        foreach ($this->orders->pendingPickupCycleIdsOfHousehold($householdId) as $cycleId) {
            $cycle = $this->cycles->get($cycleId);

            if ($cycle !== null) {
                $pending[] = new PendingPickupData($cycleId, $cycle->pickupDate()->format('Y-m-d'));
            }
        }

        usort($pending, fn (PendingPickupData $a, PendingPickupData $b) => [$a->pickupDate, $a->cycleId] <=> [$b->pickupDate, $b->cycleId]);

        return $pending;
    }
}
