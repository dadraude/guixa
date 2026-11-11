<?php

namespace Guixa\Ordering\Application;

/** A cycle where the producer's own order is sent; it carries no totals, household or other producer. */
final readonly class DashboardOpportunitySummaryData
{
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
    ) {}
}
