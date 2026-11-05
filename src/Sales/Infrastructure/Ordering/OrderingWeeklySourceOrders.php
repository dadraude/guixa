<?php

namespace Guixa\Sales\Infrastructure\Ordering;

use Guixa\Ordering\Application\ReadCycleSourceOrders;
use Guixa\Ordering\Application\ReadSourceOrderCycles;
use Guixa\Sales\Application\WeeklySourceOrders;

final class OrderingWeeklySourceOrders implements WeeklySourceOrders
{
    public function __construct(private readonly ReadSourceOrderCycles $cycles, private readonly ReadCycleSourceOrders $orders) {}

    public function cyclesOf(array $sourceOrderIds): array
    {
        return ($this->cycles)($sourceOrderIds);
    }

    public function ofCycle(string $cycleId): array
    {
        return ($this->orders)($cycleId);
    }
}
