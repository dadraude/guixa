<?php

namespace Guixa\Dashboard\Infrastructure\Ordering;

use Guixa\Dashboard\Domain\OrderOpportunitiesRepository;
use Guixa\Dashboard\Domain\ValueObject\OrderWindow;
use Guixa\Dashboard\Domain\ValueObject\SentSummary;
use Guixa\Ordering\Application\DashboardOpportunityCutoffData;
use Guixa\Ordering\Application\DashboardOpportunityScope;
use Guixa\Ordering\Application\DashboardOpportunitySummaryData;
use Guixa\Ordering\Application\DashboardOpportunityWindowData;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;

final class OrderingOrderOpportunities implements OrderOpportunitiesRepository
{
    public function __construct(private readonly ReadDashboardOrderOpportunities $read) {}

    public function openWindows(): array
    {
        return array_map(
            fn (DashboardOpportunityWindowData $window) => new OrderWindow(
                $window->cycleId,
                $window->pickupDate,
                array_map(fn (DashboardOpportunityCutoffData $cutoff) => [$cutoff->producerName, $cutoff->closesAt], $window->cutoffs),
            ),
            ($this->read)(DashboardOpportunityScope::OpenWindows),
        );
    }

    public function sentSummaries(string $producerId): array
    {
        return array_map(
            fn (DashboardOpportunitySummaryData $summary) => new SentSummary($summary->cycleId, $summary->pickupDate),
            ($this->read)(DashboardOpportunityScope::SentSummaries, $producerId),
        );
    }
}
