<?php

namespace Guixa\Catalog\Domain;

/** What Catalog asks the cycles about a product (R1). */
interface ProducerCycles
{
    /** Whether any household order has a line of the product in a cycle that is not settled. */
    public function hasUnsettledLines(string $productId): bool;
}
