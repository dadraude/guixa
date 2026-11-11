<?php

namespace Guixa\Dashboard\Application;

final readonly class DashboardData
{
    /**
     * @param  list<AttentionGroupData>  $attention
     * @param  list<OpportunityData>  $opportunities
     */
    public function __construct(public array $attention, public array $opportunities = []) {}
}
