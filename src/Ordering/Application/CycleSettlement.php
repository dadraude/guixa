<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupStatus;

/**
 * What follows a producer being distributed (R18, R24, D10). Shared by `MarkProducerDistributed` and the scheduler, so a
 * cycle settled by either drops lines, opens pickups and closes when every pickup is done.
 */
final class CycleSettlement
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly NotDeliveredMarks $marks,
    ) {}

    /** Call it in the transaction that holds the cycle `FOR UPDATE`; `$settledNow` is what `Cycle::markDistributed` returned. */
    public function __invoke(Cycle $cycle, bool $settledNow, DateTimeImmutable $now): void
    {
        if (! $cycle->isSettled()) {
            return;
        }

        foreach ($this->orders->ofCycle($cycle->id) as $order) {
            if ($settledNow) {
                $order->drop($this->marks->of($cycle, $order));
            } else {
                $order->reopenIfAllocated();
            }
            $this->orders->add($order);
        }

        $pending = array_filter($this->orders->ofCycle($cycle->id), fn (Order $order) => $order->pickup() === PickupStatus::Pending);
        $cycle->close($now, $pending === []);
    }
}
