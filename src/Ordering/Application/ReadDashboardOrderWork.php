<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ReceptionProducerNames;

/** Internal query for the dashboard, which authorises its own scopes; it takes no actor. It only reads: an unopened reception is calculated, never persisted. */
final class ReadDashboardOrderWork
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionLoader $loader,
        private readonly ReceptionProducerNames $producerNames,
    ) {}

    /** @return list<DashboardReceptionWorkData> the reception scope: every sent producer order whose reception is not distributed yet, closed cycles included */
    public function __invoke(DashboardOrderScope $scope): array
    {
        return match ($scope) {
            DashboardOrderScope::Reception => $this->reception(),
        };
    }

    /** @return list<DashboardReceptionWorkData> */
    private function reception(): array
    {
        $pendingByCycle = [];
        foreach ($this->cycles->listWithSentOrders() as $cycle) {
            $producerIds = array_values(array_map(
                fn (ProducerOrder $order) => $order->producerId,
                array_filter($cycle->producerOrders(), fn (ProducerOrder $order) => $order->isSent() && ! $cycle->isDistributed($order->producerId)),
            ));
            if ($producerIds !== []) {
                $pendingByCycle[] = [$cycle, $producerIds];
            }
        }

        $names = $this->producerNames->of($this->producerIds($pendingByCycle));
        $work = [];
        foreach ($pendingByCycle as [$cycle, $producerIds]) {
            array_push($work, ...$this->cycleWork($cycle, $producerIds, $names));
        }

        return $work;
    }

    /**
     * @param  list<array{Cycle, list<string>}>  $pendingByCycle
     * @return list<string>
     */
    private function producerIds(array $pendingByCycle): array
    {
        $ids = [];
        foreach ($pendingByCycle as [, $producerIds]) {
            foreach ($producerIds as $producerId) {
                $ids[$producerId] = $producerId;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<string>  $producerIds
     * @param  array<string, string>  $names
     * @return list<DashboardReceptionWorkData>
     */
    private function cycleWork(Cycle $cycle, array $producerIds, array $names): array
    {
        $loaded = $this->loader->loadAll($cycle, $producerIds);

        return array_map(function (string $producerId) use ($cycle, $loaded, $names) {
            $balanced = $loaded[$producerId]->reception->balancedForDistribution();

            return new DashboardReceptionWorkData(
                $cycle->id,
                $producerId,
                $names[$producerId] ?? $producerId,
                $cycle->pickupDate()->format('Y-m-d'),
                $this->stage($cycle->isValidated($producerId), $balanced),
                ! $balanced,
            );
        }, $producerIds);
    }

    private function stage(bool $validated, bool $balanced): DashboardReceptionStage
    {
        if (! $validated) {
            return DashboardReceptionStage::Check;
        }

        return $balanced ? DashboardReceptionStage::Distribute : DashboardReceptionStage::ResolveAllocations;
    }
}
