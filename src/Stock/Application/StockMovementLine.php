<?php

namespace Guixa\Stock\Application;

/** One line of a stock product's history. `type` is entry, adjustment, sale or correction. */
final readonly class StockMovementLine
{
    public function __construct(
        public string $id,
        public string $occurredAt,
        public string $type,
        public string $quantity,
        public string $balanceAfter,
        public ?string $note,
        public string $authorName,
        public ?string $cycleId,
        public ?string $pickupDate,
        public ?string $basketId,
    ) {}
}
