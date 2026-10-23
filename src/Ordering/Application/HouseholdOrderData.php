<?php

namespace Guixa\Ordering\Application;

final readonly class HouseholdOrderData
{
    /**
     * @param  list<OrderProducerData>  $producers  those that participate in the cycle
     * @param  list<OrderLineData>  $lines  in the order they were added
     */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public array $producers,
        public array $lines,
    ) {}
}
