<?php

namespace Guixa\Catalog\Infrastructure\Stock;

use Guixa\Catalog\Domain\StockAvailability;
use Guixa\Stock\Application\GetAvailableQuantities;

final class StockAvailabilityAdapter implements StockAvailability
{
    public function __construct(private readonly GetAvailableQuantities $available) {}

    public function forProducts(array $productIds): array
    {
        return ($this->available)($productIds);
    }
}
