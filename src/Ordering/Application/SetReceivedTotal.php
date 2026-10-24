<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ManualAllocationsWouldBeLost;
use Guixa\Ordering\Domain\Error\ProductNotInReception;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProportionalAllocator;
use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;

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
    ) {
        $this->allocator = new ProportionalAllocator;
    }

    /**
     * @param  string  $received  at most 3 decimals; whole units for a non-variable product
     * @param  bool  $overwriteManual  the manager confirmed losing the allocations set by hand; without it nothing is stored (R15)
     */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $productId, string $received, bool $overwriteManual = false): void
    {
        $actor->assertManager();

        $total = Quantity::fromString($received);

        // The cycle lock serialises every reception change, so the orders read here cannot change before they are saved.
        $this->transactions->run(function () use ($cycleId, $producerId, $productId, $total, $overwriteManual) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->assertReceptionEditable($producerId);
            $loaded = $this->loader->load($cycle, $producerId);

            $product = $loaded->reception->product($productId) ?? throw ProductNotInReception::withId($productId);
            $product->assertInStep($total);

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
            }

            $this->receivedTotals->set(new ReceivedTotal($cycleId, $producerId, $productId, $total));
            $cycle->clearValidation($producerId);
            $this->cycles->add($cycle);
        });
    }
}
