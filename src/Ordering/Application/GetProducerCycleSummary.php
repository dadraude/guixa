<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;

/**
 * What a producer sees of a cycle: its own products with their availability, and the total quantity per product only
 * once its order is sent, never with household names (R2, R12, S7, S8).
 */
final class GetProducerCycleSummary
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    public function __invoke(Actor $actor, string $cycleId): ProducerCycleSummaryData
    {
        $producerId = $actor->producerId ?? throw NotAllowed::forRole(Role::Producer->value);
        $actor->assertProducerOf($producerId);

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        // To a producer a cancelled cycle, or one it does not take part in, is nothing to see (R2, R4b).
        if ($cycle->isCancelled() || ! $cycle->hasProducer($producerId)) {
            throw CycleNotFound::withId($cycleId);
        }

        $sent = $cycle->isSent($producerId);

        return new ProducerCycleSummaryData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $sent,
            $sent ? $this->sentTotals($cycle, $producerId) : $this->offeredProducts($cycle, $producerId),
        );
    }

    /** @return list<ProducerProductTotalData> */
    private function offeredProducts(Cycle $cycle, string $producerId): array
    {
        $unavailable = array_flip($this->availability->unavailableIn($cycle->id));

        return array_map(
            fn ($product) => new ProducerProductTotalData($product->id, $product->name, $product->unit, ! isset($unavailable[$product->id]), null),
            $this->catalog->orderableOf($producerId),
        );
    }

    /** @return list<ProducerProductTotalData> what the producer has to deliver, the marked lines left out (R12a) */
    private function sentTotals(Cycle $cycle, string $producerId): array
    {
        $orders = $this->orders->ofCycle($cycle->id);
        $marked = [];

        foreach ($orders as $order) {
            $marked[$order->id] = $this->marks->of($cycle, $order);
        }

        // A marked line is left out of the totals, so what remains is available: availability cannot change once sent (R3c).
        return array_map(
            fn (array $total) => new ProducerProductTotalData($total['productId'], $total['name'], $total['unit'], true, $total['quantity']->toString()),
            $cycle->totalsToSend($producerId, $orders, $marked),
        );
    }
}
