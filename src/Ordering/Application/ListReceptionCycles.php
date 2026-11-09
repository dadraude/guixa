<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ReceptionProducerNames;
use Guixa\Ordering\Domain\ReceptionState;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The cycles a receiver can work on: every cycle with a sent producer order, closed ones included, newest pickup first (R8). */
final class ListReceptionCycles
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionProducerNames $producerNames,
    ) {}

    /** @return list<ReceptionCycleData> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('receive');

        $cycles = $this->cycles->listWithSentOrders();
        $names = $this->producerNames->of($this->producerIds($cycles));

        return array_map(fn (Cycle $cycle) => $this->cycleData($cycle, $names), $cycles);
    }

    /**
     * @param  list<Cycle>  $cycles
     * @return list<string>
     */
    private function producerIds(array $cycles): array
    {
        $ids = [];

        foreach ($cycles as $cycle) {
            foreach ($this->sentOrders($cycle) as $order) {
                $ids[$order->producerId] = $order->producerId;
            }
        }

        return array_values($ids);
    }

    /** @return list<ProducerOrder> */
    private function sentOrders(Cycle $cycle): array
    {
        return array_values(array_filter($cycle->producerOrders(), fn (ProducerOrder $order) => $order->isSent()));
    }

    /** @param  array<string, string>  $names */
    private function cycleData(Cycle $cycle, array $names): ReceptionCycleData
    {
        return new ReceptionCycleData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $cycle->isClosed(),
            array_map(fn (ProducerOrder $order) => new ReceptionCycleProducerData($order->producerId, $names[$order->producerId] ?? $order->producerId, $this->stateOf($cycle, $order)), $this->sentOrders($cycle)),
        );
    }

    private function stateOf(Cycle $cycle, ProducerOrder $order): ReceptionState
    {
        if ($cycle->isDistributed($order->producerId)) {
            return ReceptionState::Distributed;
        }

        return $cycle->isValidated($order->producerId) ? ReceptionState::Validated : ReceptionState::Sent;
    }
}
