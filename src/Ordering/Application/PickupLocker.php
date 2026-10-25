<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;

/** Finds the household's pickup of a settled cycle and locks its order until the surrounding transaction ends. */
final readonly class PickupLocker
{
    public function __construct(
        private CycleRepository $cycles,
        private OrderRepository $orders,
    ) {}

    public function lock(string $cycleId, string $householdId): Order
    {
        $cycle = $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if (! $cycle->isSettled()) {
            throw CycleNotSettled::withId($cycleId);
        }

        if ($this->orders->ofHousehold($cycleId, $householdId) === null) {
            throw PickupNotFound::forHousehold($cycleId, $householdId);
        }

        return $this->orders->lockFor($cycleId, $householdId);
    }
}
