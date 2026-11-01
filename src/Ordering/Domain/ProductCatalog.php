<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\OrderableProduct;

/** What Ordering asks Catalog about products when a line is set (R3, R10). */
interface ProductCatalog
{
    /** Locks a weekly Catalog product for a first reference; inactive weekly products can still enter reception. */
    public function lockWeekly(string $productId): ?OrderableProduct;

    /** The product, or null when it is unknown or deactivated, or so is its producer. */
    public function orderable(string $productId): ?OrderableProduct;

    /** @return list<OrderableProduct> a producer's orderable products by name; empty when the producer is unknown or deactivated */
    public function orderableOf(string $producerId): array;

    /** @return list<OrderableProduct> every product of the producer by name, deactivated ones included (R14) */
    public function allOf(string $producerId): array;
}
