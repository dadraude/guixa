<?php

namespace Guixa\Ordering\Application;

/** One row of the pickups list: a household, where its pickup stands and what it costs so far (R19). */
final readonly class PickupSummaryData
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $status,
        public int $totalCents,
    ) {}
}
