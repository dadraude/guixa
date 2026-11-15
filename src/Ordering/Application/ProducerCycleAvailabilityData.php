<?php

namespace Guixa\Ordering\Application;

final readonly class ProducerCycleAvailabilityData
{
    /** @param  list<ProductAvailabilityData>  $products  sorted by name */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $canChange,
        public array $products,
    ) {}
}
