<?php

namespace Guixa\Dashboard\Application;

use DateTimeImmutable;

final readonly class DashboardData
{
    /**
     * @param  list<AttentionGroupData>  $attention
     * @param  list<OpportunityData>  $opportunities
     * @param  DateTimeImmutable  $readAt  the instant all source reads had succeeded (R15)
     * @param  bool  $showCaughtUp  a context exists and the complete successful read found no pending work
     * @param  bool  $showNoContext  the account has no household, producer or staff context
     */
    public function __construct(public array $attention, public array $opportunities, public DateTimeImmutable $readAt, public bool $showCaughtUp = false, public bool $showNoContext = false) {}
}
