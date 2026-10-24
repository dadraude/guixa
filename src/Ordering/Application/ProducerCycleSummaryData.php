<?php

namespace Guixa\Ordering\Application;

final readonly class ProducerCycleSummaryData
{
    /** @param  list<ProducerProductTotalData>  $products  sorted by name */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $sent,
        public array $products,
    ) {}
}
