<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

/** Scheduled every minute (D10); it takes no actor. */
final class SendEmptyProducerOrders
{
    private readonly NotDeliveredMarks $marks;

    private readonly CycleSettlement $settlement;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        SalesBaskets $baskets,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
        $this->settlement = new CycleSettlement($orders, $this->marks, $baskets);
    }

    /**
     * Sends every participating, closed producer order with no line to send, then validates and distributes it, settling
     * the cycle through `CycleSettlement` like `MarkProducerDistributed`; nobody is notified (R12a, D10).
     */
    public function __invoke(): void
    {
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        foreach ($this->cycles->list() as $listed) {
            if ($listed->isCancelled() || $listed->producersReadyToSend($now) === []) {
                continue;
            }

            $this->transactions->run(fn () => $this->sendEmptyOrdersOf($listed->id, $now));
        }
    }

    private function sendEmptyOrdersOf(string $cycleId, DateTimeImmutable $now): void
    {
        // Read again under the lock: a manager may have sent or cancelled in between.
        $cycle = $this->cycles->getForUpdate($cycleId);

        if ($cycle === null) {
            return;
        }

        $orders = $this->orders->ofCycle($cycleId);
        $marked = $this->marks->derivedOfAll($cycle, $orders);

        foreach ($cycle->producersReadyToSend($now) as $producerId) {
            if ($cycle->totalsToSend($producerId, $orders, $marked) !== []) {
                continue;
            }

            $cycle->send($producerId, $now);

            foreach ($cycle->markedLines($producerId, $orders, $marked) as $orderId => $productIds) {
                $this->orders->markNotDelivered($orderId, $productIds);
            }

            // With no line to send nothing was received, so the reception is balanced.
            $cycle->validate($producerId, $now);
            $settledNow = $cycle->markDistributed($producerId, true, $now);
            ($this->settlement)($cycle, $settledNow, $now);
            $this->cycles->add($cycle);
        }
    }
}
