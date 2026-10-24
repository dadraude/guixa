<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionProductData
{
    /**
     * @param  string  $delivered  the allocations as adjusted at pickup (R19)
     * @param  string  $difference  received minus delivered, signed when negative (R17)
     * @param  bool  $balancedForDistribution  received equals allocated, ignoring pickup adjustments (R18)
     * @param  list<ReceptionAllocationData>  $allocations  by household name
     */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
        public bool $nobodyOrdered,
        public string $ordered,
        public string $received,
        public string $allocated,
        public string $delivered,
        public string $difference,
        public bool $balanced,
        public bool $balancedForDistribution,
        public bool $hasManualAllocations,
        public bool $hasAdjustments,
        public array $allocations,
    ) {}
}
