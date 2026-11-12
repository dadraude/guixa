<?php

namespace Guixa\Dashboard\Application;

use DateTimeImmutable;

final readonly class DashboardData
{
    /**
     * @param  list<AttentionGroupData>  $attention
     * @param  list<OpportunityData>  $opportunities
     * @param  DateTimeImmutable  $readAt  the instant all source reads had succeeded (R15)
     */
    public function __construct(public array $attention, public array $opportunities, public DateTimeImmutable $readAt) {}
}
