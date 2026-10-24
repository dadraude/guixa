<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderRepository;

/**
 * What follows a producer being distributed (R18, R24, D10). Shared by `MarkProducerDistributed` and the scheduler, so a
 * cycle settled by either drops lines and opens pickups the same way. Reopening after settlement and closing arrive with T30.
 */
final class CycleSettlement
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly NotDeliveredMarks $marks,
    ) {}

    /** Call it in the transaction that holds the cycle `FOR UPDATE`; `$settledNow` is what `Cycle::markDistributed` returned. */
    public function __invoke(Cycle $cycle, bool $settledNow): void
    {
        if (! $settledNow) {
            return;
        }

        foreach ($this->orders->ofCycle($cycle->id) as $order) {
            $order->drop($this->marks->of($cycle, $order));
            $this->orders->add($order);
        }
    }
}
