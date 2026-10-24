<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;

/** Records what a household really takes home of a product, e.g. the weight of a package; the allocation is kept (R19, S14). */
final class AdjustDeliveredQuantity
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @param  string  $quantity  at most 3 decimals, zero or more; whole units for a non-variable product */
    public function __invoke(Actor $actor, string $cycleId, string $householdId, string $productId, string $quantity): void
    {
        $actor->assertManager();

        $delivered = Quantity::fromString($quantity);

        $this->transactions->run(function () use ($cycleId, $householdId, $productId, $delivered) {
            $cycle = $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);

            if (! $cycle->isSettled()) {
                throw CycleNotSettled::withId($cycleId);
            }

            if ($this->orders->ofHousehold($cycleId, $householdId) === null) {
                throw PickupNotFound::forHousehold($cycleId, $householdId);
            }

            $order = $this->orders->lockFor($cycleId, $householdId);
            $order->adjustDelivered($productId, $delivered);
            $this->orders->add($order);
        });
    }
}
