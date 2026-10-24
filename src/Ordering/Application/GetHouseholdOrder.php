<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;

final class GetHouseholdOrder
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $cycleId): HouseholdOrderData
    {
        $householdId = $actor->householdId ?? throw NoHousehold::create();
        $actor->assertMemberOf($householdId);

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $participating = array_filter($cycle->producerOrders(), fn (ProducerOrder $order) => $order->isParticipating());
        usort($participating, fn (ProducerOrder $a, ProducerOrder $b) => $a->producerId <=> $b->producerId);

        $unavailable = $this->availability->unavailableIn($cycleId);

        $producers = array_map(fn (ProducerOrder $order) => new OrderProducerData(
            $order->producerId,
            $cycle->isOpenFor($order->producerId, $now),
            array_map(
                OfferedProductData::of(...),
                array_values(array_filter(
                    $this->catalog->orderableOf($order->producerId),
                    fn (OrderableProduct $product) => ! in_array($product->id, $unavailable, true),
                )),
            ),
        ), $participating);

        $order = $this->orders->ofHousehold($cycleId, $householdId);
        $marked = $order === null ? [] : (new NotDeliveredMarks($this->catalog, $this->availability, $this->orders))->of($cycle, $order);
        $lines = $order?->orderedLines() ?? [];

        return new HouseholdOrderData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $cycle->isCancelled(),
            $producers,
            array_map(fn (OrderLine $line) => OrderLineData::of($line, isset($marked[$line->productId])), $lines),
        );
    }
}
