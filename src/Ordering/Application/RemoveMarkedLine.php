<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class RemoveMarkedLine
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        private readonly TransactionRunner $transactions,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    /** A member removes a line marked *will not be delivered*, even after its producer's cutoff (R3b, R8). */
    public function __invoke(Actor $actor, string $cycleId, string $productId): void
    {
        $householdId = $actor->householdId ?? throw NoHousehold::create();
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        $this->transactions->run(function () use ($householdId, $cycleId, $productId) {
            $cycle = $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->assertNotClosed();
            $order = $this->orders->lockFor($cycleId, $householdId);

            $order->removeMarkedLine($productId, $this->marks->of($cycle, $order));
            $this->orders->add($order);
        });
    }
}
