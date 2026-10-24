<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionProductData
{
    /**
     * @param  string  $difference  received minus allocated, signed when negative (R17)
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
        public string $difference,
        public bool $balanced,
        public bool $hasManualAllocations,
        public array $allocations,
    ) {}
}
