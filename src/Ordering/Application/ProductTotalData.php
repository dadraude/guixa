<?php

namespace Guixa\Ordering\Application;

final readonly class ProductTotalData
{
    /** @param  list<HouseholdQuantityData>  $households  sorted by household name */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public string $quantity,
        public array $households,
    ) {}
}
