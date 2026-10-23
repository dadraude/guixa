<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\OrderableProduct;

/** What Ordering asks Catalog about products when a line is set (R3, R10). */
interface ProductCatalog
{
    /** The product, or null when it is unknown or deactivated, or so is its producer. */
    public function orderable(string $productId): ?OrderableProduct;

    /** @return list<OrderableProduct> a producer's orderable products by name; empty when the producer is unknown or deactivated */
    public function orderableOf(string $producerId): array;
}
