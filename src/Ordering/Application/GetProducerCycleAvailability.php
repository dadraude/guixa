<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** A producer's active weekly products and whether each is available in a cycle, never the cycle's totals (D9, R14). */
final class GetProducerCycleAvailability
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $producerId): ProducerCycleAvailabilityData
    {
        Gate::forUser($actor)->authorize('set-availability', $producerId);

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if ($cycle->isCancelled() || ! $cycle->hasProducer($producerId)) {
            throw CycleNotFound::withId($cycleId);
        }

        $unavailable = array_flip($this->availability->unavailableIn($cycle->id));

        return new ProducerCycleAvailabilityData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            ! $cycle->isSent($producerId),
            array_map(
                fn (OrderableProduct $product) => new ProductAvailabilityData($product->id, $product->name, $product->unit, ! isset($unavailable[$product->id])),
                $this->catalog->orderableOf($producerId),
            ),
        );
    }
}
