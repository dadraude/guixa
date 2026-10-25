<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupStatus;

/** Finds the household's pickup of a settled cycle and locks its order until the surrounding transaction ends. */
final readonly class PickupLocker
{
    public function __construct(
        private CycleRepository $cycles,
        private OrderRepository $orders,
    ) {}

    public function lock(string $cycleId, string $householdId, bool $completing = false): Order
    {
        // Finalization can close the cycle: lock exclusively before any order lock to avoid a shared-lock upgrade deadlock.
        $cycle = ($completing ? $this->cycles->getForUpdate($cycleId) : $this->cycles->getForShare($cycleId))
            ?? throw CycleNotFound::withId($cycleId);

        if (! $cycle->isSettled()) {
            throw CycleNotSettled::withId($cycleId);
        }

        if ($this->orders->ofHousehold($cycleId, $householdId) === null) {
            throw PickupNotFound::forHousehold($cycleId, $householdId);
        }

        return $this->orders->lockFor($cycleId, $householdId);
    }

    public function closeCycleIfDone(string $cycleId, DateTimeImmutable $now): void
    {
        $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
        $pending = array_filter($this->orders->ofCycle($cycleId), fn (Order $order) => $order->pickup() === PickupStatus::Pending);
        $cycle->close($now, $pending === []);
        $this->cycles->add($cycle);
    }
}
