<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\WeeklyBasketStates;

/**
 * What follows a producer being distributed (R18, R24, D10). Shared by `MarkProducerDistributed` and the scheduler, so a
 * cycle settled by either drops lines, creates one base basket per order in Sales and closes when every weekly basket, base or late, is finished.
 */
final class CycleSettlement
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly NotDeliveredMarks $marks,
        private readonly SalesBaskets $baskets,
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
                $this->orders->add($order);
            }
            $this->followOrder($order, $settledNow);
        }

        $cycle->close($now, WeeklyBasketStates::areFinished($this->baskets->snapshot($cycle->id)->states()));
    }

    /** An order without lines has no base; the first settlement creates it and a later one makes unvalidated bases follow the allocations. */
    private function followOrder(Order $order, bool $settledNow): void
    {
        if ($order->lines() === []) {
            return;
        }

        $source = WeeklySourceData::of($order);
        $settledNow ? $this->baskets->createWeeklyBase($source) : $this->baskets->syncWeeklyBase($source);
    }
}
