<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class SetProductAvailability
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
        private readonly TransactionRunner $transactions,
    ) {}

    /** The producer marks its own product unavailable in a cycle, or available again (R3c). */
    public function __invoke(Actor $actor, string $cycleId, string $productId, bool $available): void
    {
        $product = $this->catalog->orderable($productId) ?? throw ProductNotOrderable::withId($productId);
        $actor->assertProducerOf($product->producerId);

        $this->transactions->run(function () use ($cycleId, $productId, $available) {
            // Shared lock on the cycle: a send cannot slip in while the mark changes (R3c).
            $this->cycles->getForShare($cycleId) ?? throw CycleNotFound::withId($cycleId);

            $available
                ? $this->availability->markAvailable($cycleId, $productId)
                : $this->availability->markUnavailable($cycleId, $productId);
        });
    }
}
