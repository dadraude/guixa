<?php

namespace Guixa\Stock\Application;

/** One page of a product's history, newest first, with the number of movements in the whole history and the product header. */
final readonly class StockMovementPage
{
    /** @param list<StockMovementLine> $lines */
    public function __construct(
        public array $lines,
        public int $total,
        public string $productName,
        public string $unit,
        public string $available,
    ) {}
}
