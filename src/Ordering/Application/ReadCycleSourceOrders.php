<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderRepository;

/** Internal query for Sales: the orders of a cycle, whose weekly baskets Sales finds by source order; it takes no actor. */
final class ReadCycleSourceOrders
{
    public function __construct(private readonly OrderRepository $orders) {}

    /** @return list<string> ids of every order of the cycle, cancelled ones included */
    public function __invoke(string $cycleId): array
    {
        return $this->orders->idsOfCycle($cycleId);
    }
}
