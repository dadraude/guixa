<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;

/** Internal query for Sales, which stores a source order id and no cycle of its own: the cycle each order belongs to; it takes no actor. */
final class ReadSourceOrderCycles
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * @param  list<string>  $orderIds
     * @return array<string, string> cycle id by order id; unknown orders are left out
     */
    public function __invoke(array $orderIds): array
    {
        return $this->orders->cycleIdsOfOrders($orderIds);
    }
}
