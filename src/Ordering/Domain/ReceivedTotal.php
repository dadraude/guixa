<?php

namespace Guixa\Ordering\Domain;

use Guixa\Shared\Domain\Quantity;

/** What a manager recorded as received of a product in a cycle; without one the product counts as received in full (R14). */
final readonly class ReceivedTotal
{
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $productId,
        public Quantity $received,
    ) {}
}
