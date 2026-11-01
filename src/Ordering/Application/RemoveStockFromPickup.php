<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

/** Removes a whole stock line from a pending pickup, whether or not its product was deactivated since (R20, S55). */
final class RemoveStockFromPickup
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId, string $stockProductId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($cycleId, $householdId, $stockProductId) {
            $order = $this->pickups->lock($cycleId, $householdId);

            $order->removeStock($stockProductId);
            $this->orders->add($order);
        });
    }
}
