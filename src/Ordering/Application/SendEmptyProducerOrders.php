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

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    /**
     * Sends every participating, closed producer order with no line to send; nobody is notified (R12a). Validating and
     * distributing them belongs to the reception tasks.
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
        $marked = [];

        foreach ($orders as $order) {
            $marked[$order->id] = $this->marks->derivedOf($cycle, $order);
        }

        foreach ($cycle->producersReadyToSend($now) as $producerId) {
            if ($cycle->totalsToSend($producerId, $orders, $marked) !== []) {
                continue;
            }

            $cycle->send($producerId, $now);

            foreach ($cycle->markedLines($producerId, $orders, $marked) as $orderId => $productIds) {
                $this->orders->markNotDelivered($orderId, $productIds);
            }
        }

        $this->cycles->add($cycle);
    }
}
