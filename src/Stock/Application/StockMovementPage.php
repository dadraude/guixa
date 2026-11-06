<?php

namespace Guixa\Stock\Application;

/** One page of a product's history, newest first, and the number of movements in the whole history. */
final readonly class StockMovementPage
{
    /** @param list<StockMovementLine> $lines */
    public function __construct(public array $lines, public int $total) {}
}
