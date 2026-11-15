<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerNotifier;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ReceptionProducerNames;
use Guixa\Shared\Domain\Clock;

/** Internal query for the dashboard, which authorises its own scopes; it takes no actor. It only reads: an unopened reception is calculated, never persisted. */
final class ReadDashboardOrderWork
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionLoader $loader,
        private readonly ReceptionProducerNames $producerNames,
        private readonly Clock $clock,
        private readonly SendableTotals $sendableTotals,
        private readonly ProducerNotifier $notifier,
    ) {}

    /**
     * Reception: every sent producer order whose reception is not distributed yet, closed cycles included.
     * ManualSend: the orders 001 R12a lets a manager send now, by the rule `ListCycles` uses plus the producer's account.
     *
     * @return ($scope is DashboardOrderScope::Reception ? list<DashboardReceptionWorkData> : list<DashboardSendWorkData>)
     */
    public function __invoke(DashboardOrderScope $scope): array
    {
        return match ($scope) {
            DashboardOrderScope::Reception => $this->reception(),
            DashboardOrderScope::ManualSend => $this->manualSend(),
        };
    }

    /** @return list<DashboardSendWorkData> */
    private function manualSend(): array
    {
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $sendable = [];
        foreach ($this->cycles->list() as $cycle) {
            $producerIds = array_values(array_filter(
                ($this->sendableTotals)($cycle, $cycle->producersReadyToSend($now)),
                fn (string $producerId) => $this->notifier->canNotify($producerId),
            ));
            if ($producerIds !== []) {
                $sendable[] = [$cycle, $producerIds];
            }
        }

        $names = $this->producerNames->of($this->producerIds($sendable));
        $work = [];
        foreach ($sendable as [$cycle, $producerIds]) {
            foreach ($producerIds as $producerId) {
                $work[] = new DashboardSendWorkData($cycle->id, $producerId, $names[$producerId] ?? $producerId, $cycle->pickupDate()->format('Y-m-d'));
            }
        }

        return $work;
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
