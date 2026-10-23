<?php

namespace Guixa\Ordering\Application;

final readonly class ProducerTotalsData
{
    /** @param  list<ProductTotalData>  $products  sorted by name */
    public function __construct(
        public string $producerId,
        public array $products,
    ) {}
}
