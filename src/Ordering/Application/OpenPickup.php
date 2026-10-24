<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\HouseholdInactive;
use Guixa\Ordering\Domain\Error\HouseholdNotFound;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

/** Opens the pickup of an active household that has none in a settled cycle, e.g. to sell it stock (R19, S18). */
final class OpenPickup
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Households $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($cycleId, $householdId) {
            $cycle = $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);

            if (! $cycle->isSettled()) {
                throw CycleNotSettled::withId($cycleId);
            }

            $household = $this->households->find([$householdId])[$householdId] ?? throw HouseholdNotFound::withId($householdId);

            if (! $household->active) {
                throw HouseholdInactive::withId($householdId);
            }

            $order = $this->orders->lockFor($cycleId, $householdId);
            $order->openPickup();
            $this->orders->add($order);
        });
    }
}
