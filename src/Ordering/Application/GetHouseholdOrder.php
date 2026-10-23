<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;

final class GetHouseholdOrder
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
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

        $producers = array_map(fn (ProducerOrder $order) => new OrderProducerData(
            $order->producerId,
            $cycle->isOpenFor($order->producerId, $now),
            array_map(OfferedProductData::of(...), $this->catalog->orderableOf($order->producerId)),
        ), $participating);

        $lines = $this->orders->ofHousehold($cycleId, $householdId)?->lines() ?? [];

        return new HouseholdOrderData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $producers,
            array_map(OrderLineData::of(...), $lines),
        );
    }
}
