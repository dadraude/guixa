<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ManualAllocationsWouldBeLost;
use Guixa\Ordering\Domain\Error\ProductNotInReception;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProportionalAllocator;
use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

/** Way A: sets what was received of a product and shares it among the households that ordered it (R15, S10-S12, S31). */
final class SetReceivedTotal
{
    private readonly ProportionalAllocator $allocator;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ReceptionLoader $loader,
        private readonly ReceivedTotalRepository $receivedTotals,
        private readonly TransactionRunner $transactions,
        private readonly SalesBaskets $baskets,
    ) {
        $this->allocator = new ProportionalAllocator;
    }

    /**
     * @param  string  $received  at most 3 decimals; whole units for a non-variable product
     * @param  bool  $overwriteManual  the manager confirmed losing the allocations set by hand; without it nothing is stored (R15)
     */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $productId, string $received, bool $overwriteManual = false): void
    {
        Gate::forUser($actor)->authorize('receive');

        $total = Quantity::fromString($received);

        // The cycle lock serialises every reception change, so the orders read here cannot change before they are saved.
        $this->transactions->run(function () use ($cycleId, $producerId, $productId, $total, $overwriteManual) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->assertReceptionEditable($producerId);
            $loaded = $this->loader->load($cycle, $producerId);

            $product = $loaded->reception->product($productId) ?? throw ProductNotInReception::withId($productId);
            $this->loader->lockWeekly($productId) ?? throw ProductNotInReception::withId($productId);
            $product->assertInStep($total);

            $lateArrival = $cycle->isSettled() && $product->received->isZero() && ! $total->isZero();

            // A late arrival goes to a draft; any other change must not touch a product a validated basket has a line of.
            if (! $lateArrival) {
                $loaded->assertRedistributable($cycle, $productId);
            }

            $shares = $this->allocator->allocate($total, $product->step(), $product->claims());

            $manual = $product->manuallyAllocatedHouseholds();

            if ($manual !== [] && ! $overwriteManual) {
                throw ManualAllocationsWouldBeLost::of($productId, $manual);
            }

            foreach ($loaded->orders as $order) {
                if (! $order->hasLine($productId) || isset($loaded->marksByOrder[$order->id][$productId])) {
                    continue;
                }

                $order->reallocate($productId, $shares[$order->householdId] ?? Quantity::ofThousandths(0));
                $this->orders->add($order);
                // A household with a validated basket has its share in a late weekly basket (no validated basket has a line of the product).
                $toLateBasket = $lateArrival || $loaded->baskets->isValidated($order->householdId);
                $this->followAllocation($cycle, $order, $toLateBasket ? $productId : null);
            }

            $this->receivedTotals->set(new ReceivedTotal($cycleId, $producerId, $productId, $total));
            $cycle->clearValidation($producerId);
            $this->cycles->add($cycle);
        });
    }

    /** After settlement an unvalidated base follows the allocation at once, so the reception compares real actual quantities. */
    private function followAllocation(Cycle $cycle, Order $order, ?string $lateProductId): void
    {
        if (! $cycle->isSettled()) {
            return;
        }

        $source = WeeklySourceData::of($order);
        $lateProductId === null ? $this->baskets->syncWeeklyBase($source) : $this->baskets->addLateArrival($source, [$lateProductId]);
    }
}
