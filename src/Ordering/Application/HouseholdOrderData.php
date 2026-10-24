<?php

namespace Guixa\Ordering\Application;

final readonly class HouseholdOrderData
{
    /**
     * @param  list<OrderProducerData>  $producers  those that participate in the cycle
     * @param  list<OrderLineData>  $lines  in the order they were added
     * @param  ?PickupData  $pickup  once the cycle is settled and the household has one (R23a)
     */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $cancelled,
        public array $producers,
        public array $lines,
        public ?PickupData $pickup = null,
    ) {}
}
