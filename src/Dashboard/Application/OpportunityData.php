<?php

namespace Guixa\Dashboard\Application;

final readonly class OpportunityData
{
    /**
     * @param  string  $kind  an OpportunityKind value
     * @param  ?string  $cycleId  the cycle the link opens; null for Catalogue maintenance
     * @param  list<array{string, string}>  $cutoffs  `[producerName, closesAt]`, only for an open ordering window
     */
    public function __construct(
        public string $key,
        public string $kind,
        public OpportunityArea $area,
        public ?string $cycleId,
        public ?string $scheduledDate,
        public array $cutoffs = [],
    ) {}
}
