<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ReceptionProducerNames;
use Guixa\Shared\Domain\Clock;

/** Internal query for the dashboard, which gates role and association itself; it takes only the current context ID and no actor. */
final class ReadDashboardOrderOpportunities
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionProducerNames $producerNames,
        private readonly Clock $clock,
    ) {}

    /**
     * OpenWindows takes no ID: ordering windows are cooperative-wide. SentSummaries takes the current producer ID.
     *
     * @return ($scope is DashboardOpportunityScope::OpenWindows ? list<DashboardOpportunityWindowData> : list<DashboardOpportunitySummaryData>)
     */
    public function __invoke(DashboardOpportunityScope $scope, ?string $producerId = null): array
    {
        return match ($scope) {
            DashboardOpportunityScope::OpenWindows => $this->openWindows(),
            DashboardOpportunityScope::SentSummaries => $this->sentSummaries($producerId ?? throw new \InvalidArgumentException('Sent summaries need the current producer ID.')),
        };
    }

    /** @return list<DashboardOpportunityWindowData> the cycles still open for members, by pickup date; a saved order changes nothing here (R11) */
    private function openWindows(): array
    {
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $open = [];
        foreach ($this->cycles->list() as $cycle) {
            if (! $cycle->isCancelled() && $cycle->isOpenForMembers($now)) {
                $open[] = [$cycle, $this->openCutoffs($cycle, $now)];
            }
        }

        $names = $this->producerNames->of($this->producerIds($open));

        return array_map(
            fn (array $entry) => new DashboardOpportunityWindowData(
                $entry[0]->id,
                $entry[0]->pickupDate()->format('Y-m-d'),
                $this->cutoffData($entry[1], $names),
            ),
            $open,
        );
    }

    /** @return list<DashboardOpportunitySummaryData> the producer's own sent orders in cycles that are not cancelled and whose pickup is today or later in the cooperative's calendar */
    private function sentSummaries(string $producerId): array
    {
        $today = $this->clock->now()->setTimezone($this->clock->timezone())->format('Y-m-d');

        $sent = array_filter(
            $this->cycles->listWithSentOrders(),
            fn (Cycle $cycle) => ! $cycle->isCancelled() && $cycle->isSent($producerId) && $cycle->pickupDate()->format('Y-m-d') >= $today,
        );

        return array_values(array_map(
            fn (Cycle $cycle) => new DashboardOpportunitySummaryData($cycle->id, $cycle->pickupDate()->format('Y-m-d')),
            $sent,
        ));
    }

    /** @return array<string, \DateTimeImmutable> closing time by producer, for open producers that have a cutoff */
    private function openCutoffs(Cycle $cycle, \DateTimeImmutable $now): array
    {
        $cutoffs = [];
        foreach ($cycle->producerOrders() as $order) {
            if ($cycle->isOpenFor($order->producerId, $now) && $order->cutoff() !== null) {
                $cutoffs[$order->producerId] = $order->cutoff();
            }
        }
        asort($cutoffs);

        return $cutoffs;
    }

    /**
     * @param  array<string, \DateTimeImmutable>  $cutoffs
     * @param  array<string, string>  $names
     * @return list<DashboardOpportunityCutoffData>
     */
    private function cutoffData(array $cutoffs, array $names): array
    {
        $data = [];
        foreach ($cutoffs as $producerId => $closesAt) {
            $data[] = new DashboardOpportunityCutoffData($names[$producerId] ?? $producerId, $closesAt->setTimezone($this->clock->timezone())->format('Y-m-d H:i'));
        }

        return $data;
    }

    /**
     * @param  list<array{Cycle, array<string, \DateTimeImmutable>}>  $open
     * @return list<string>
     */
    private function producerIds(array $open): array
    {
        $ids = [];
        foreach ($open as [, $cutoffs]) {
            foreach (array_keys($cutoffs) as $producerId) {
                $ids[$producerId] = $producerId;
            }
        }

        return array_values($ids);
    }
}
