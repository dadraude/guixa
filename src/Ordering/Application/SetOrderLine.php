<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;

final class SetOrderLine
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param  string  $quantity  at most 3 decimals; zero removes the line (R9) */
    public function __invoke(Actor $actor, string $cycleId, string $productId, string $quantity): void
    {
        $householdId = $actor->householdId ?? throw NoHousehold::create();
        $actor->assertMemberOf($householdId);

        $ordered = Quantity::fromString($quantity);
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($actor, $householdId, $cycleId, $productId, $ordered, $now) {
            // Shared lock on the cycle: a cutoff change or a send cannot slip in while the line is saved (R6b, R8).
            $cycle = $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $order = $this->orders->lockFor($cycleId, $householdId);

            if ($ordered->isZero()) {
                $order->removeLine($productId, $cycle, $now);
            } else {
                $this->catalog->lockWeekly($productId) ?? throw ProductNotOrderable::withId($productId);
                $product = $this->catalog->orderable($productId) ?? throw ProductNotOrderable::withId($productId);

                if (in_array($productId, $this->availability->unavailableIn($cycleId), true)) {
                    throw ProductNotOrderable::unavailableInCycle($productId, $cycleId);
                }

                $order->setLine($product, $ordered, $cycle, $this->orders->nextId(), $actor->accountId, $now);
            }

            $this->orders->add($order);
        });
    }
}
