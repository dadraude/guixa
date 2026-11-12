<?php

namespace Guixa\Dashboard\Infrastructure\Ordering;

use Guixa\Dashboard\Domain\OrderOpportunitiesRepository;
use Guixa\Dashboard\Domain\ValueObject\OrderWindow;
use Guixa\Dashboard\Domain\ValueObject\SentSummary;
use Guixa\Dashboard\Infrastructure\TranslatesReadFailures;
use Guixa\Ordering\Application\DashboardOpportunityCutoffData;
use Guixa\Ordering\Application\DashboardOpportunityScope;
use Guixa\Ordering\Application\DashboardOpportunitySummaryData;
use Guixa\Ordering\Application\DashboardOpportunityWindowData;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;

final class OrderingOrderOpportunities implements OrderOpportunitiesRepository
{
    use TranslatesReadFailures;

    public function __construct(private readonly ReadDashboardOrderOpportunities $read) {}

    public function openWindows(): array
    {
        return array_map(
            fn (DashboardOpportunityWindowData $window) => new OrderWindow(
                $window->cycleId,
                $window->pickupDate,
                array_map(fn (DashboardOpportunityCutoffData $cutoff) => [$cutoff->producerName, $cutoff->closesAt], $window->cutoffs),
            ),
            $this->guarded('open ordering windows', fn () => ($this->read)(DashboardOpportunityScope::OpenWindows)),
        );
    }

    public function sentSummaries(string $producerId): array
    {
        return array_map(
            fn (DashboardOpportunitySummaryData $summary) => new SentSummary($summary->cycleId, $summary->pickupDate),
            $this->guarded('sent summaries', fn () => ($this->read)(DashboardOpportunityScope::SentSummaries, $producerId)),
        );
    }
}
