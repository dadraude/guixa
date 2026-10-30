<?php

namespace Guixa\Sales\Infrastructure\Stock;

use Guixa\Sales\Domain\StockQuantities;
use Guixa\Stock\Application\GetAvailableQuantities;

final class StockQuantitiesAdapter implements StockQuantities
{
    public function __construct(private readonly GetAvailableQuantities $available) {}

    public function forProducts(array $ids): array
    {
        return ($this->available)($ids);
    }
}
