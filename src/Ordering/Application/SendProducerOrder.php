<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerHasNoAccount;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProducerNotifier;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class SendProducerOrder
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        private readonly ProducerNotifier $notifier,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    /**
     * Freezes the producer's cutoff, stores the marked lines as not delivered and tells the producer what to deliver
     * after the commit (R6b, R12a, D9). The cycle is locked `FOR UPDATE`, so a line change racing the send either
     * landed before it or is rejected.
     */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($cycleId, $producerId, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->send($producerId, $now);

            $orders = $this->orders->ofCycle($cycleId);
            $marked = [];

            foreach ($orders as $order) {
                $marked[$order->id] = $this->marks->derivedOf($cycle, $order);
            }

            $totals = $cycle->totalsToSend($producerId, $orders, $marked);

            if ($totals !== [] && ! $this->notifier->canNotify($producerId)) {
                throw ProducerHasNoAccount::withId($producerId);
            }

            foreach ($cycle->markedLines($producerId, $orders, $marked) as $orderId => $productIds) {
                $this->orders->markNotDelivered($orderId, $productIds);
            }

            $this->cycles->add($cycle);

            if ($totals !== []) {
                $this->transactions->afterCommit(fn () => $this->notifier->notify($producerId, $cycle->pickupDate(), $totals));
            }
        });
    }
}
