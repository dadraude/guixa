<?php

namespace Guixa\Ordering\Application;

/** A producer taking part in the cycle: whether members can still order from it (R8) and what it offers. */
final readonly class OrderProducerData
{
    /** @param  list<OfferedProductData>  $products  by name */
    public function __construct(
        public string $producerId,
        public bool $open,
        public array $products,
    ) {}
}
