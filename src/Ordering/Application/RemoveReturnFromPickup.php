<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

/** Removes a whole return line from a pending pickup (R20a). */
final class RemoveReturnFromPickup
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId, string $returnItemId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($cycleId, $householdId, $returnItemId) {
            $order = $this->pickups->lock($cycleId, $householdId);

            $order->removeReturn($returnItemId);
            $this->orders->add($order);
        });
    }
}
