<?php

namespace Guixa\Ordering\Application;

final readonly class ProductAvailabilityData
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $available,
    ) {}
}
