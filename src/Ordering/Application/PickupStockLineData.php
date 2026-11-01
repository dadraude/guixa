<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\StockLine;

/** A stock line of a pickup: the product as it was when added, the quantity and what it costs, in cents (R20). */
final readonly class PickupStockLineData
{
    public function __construct(
        public string $stockProductId,
        public string $name,
        public string $unit,
        public bool $variable,
        public int $unitPriceCents,
        public string $quantity,
        public int $amountCents,
    ) {}

    public static function of(StockLine $line): self
    {
        return new self(
            $line->stockProductId,
            $line->name,
            $line->unit,
            $line->variable,
            $line->unitPrice->cents,
            $line->quantity->toString(),
            $line->amount()->cents,
        );
    }
}
