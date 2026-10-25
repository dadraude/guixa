<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Error\StockProductNotFound;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\StockCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;

/** Adds a stock product to a pending pickup, checking the available stock (R20, S17). */
final class AddStockToPickup
{
    public function __construct(
        private readonly PickupLocker $pickups,
        private readonly OrderRepository $orders,
        private readonly StockCatalog $stock,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @param  string  $quantity  more than zero, at most 3 decimals; whole units for a non-variable product */
    public function __invoke(Actor $actor, string $cycleId, string $householdId, string $stockProductId, string $quantity): void
    {
        $actor->assertManager();

        $quantity = Quantity::fromString($quantity);

        $this->transactions->run(function () use ($cycleId, $householdId, $stockProductId, $quantity) {
            $order = $this->pickups->lock($cycleId, $householdId);
            $offer = $this->stock->offer($stockProductId) ?? throw StockProductNotFound::withId($stockProductId);

            $order->addStock($offer, $quantity, $this->orders->nextId());
            $this->orders->add($order);
        });
    }
}
