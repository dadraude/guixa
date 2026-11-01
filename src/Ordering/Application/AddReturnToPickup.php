<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Error\ReturnItemNotFound;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ReturnItemCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

/** Adds returned items to a pending pickup, each crediting its amount (R20a). */
final class AddReturnToPickup
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly ReturnItemCatalog $returnItems,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @param  int  $quantity  whole units, more than zero */
    public function __invoke(Actor $actor, string $cycleId, string $householdId, string $returnItemId, int $quantity): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($cycleId, $householdId, $returnItemId, $quantity) {
            $order = $this->pickups->lock($cycleId, $householdId);
            $offer = $this->returnItems->find($returnItemId) ?? throw ReturnItemNotFound::withId($returnItemId);

            $order->addReturn($offer, $quantity, $this->orders->nextId());
            $this->orders->add($order);
        });
    }
}
