<?php

namespace Guixa\Ordering\Application;

final readonly class ProducerProductTotalData
{
    /** @param  ?string  $quantity  the total to deliver, null while the producer's order is not sent (R12) */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $available,
        public ?string $quantity,
    ) {}
}
