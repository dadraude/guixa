<?php

namespace Guixa\Ordering\Application;

final readonly class CycleSummaryData
{
    /** @param  list<ProducerTotalsData>  $producers  only those with ordered lines; none when cancelled */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public array $producers,
        public bool $cancelled,
    ) {}
}
