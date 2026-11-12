<?php

namespace Guixa\Ordering\Application;

/** A cycle a household can still order in, with the cutoffs that apply to it. */
final readonly class DashboardOpportunityWindowData
{
    /** @param  list<DashboardOpportunityCutoffData>  $cutoffs by closing time, earliest first */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public array $cutoffs,
    ) {}
}
