<?php

namespace Guixa\Stock\Domain;

use Guixa\Stock\Domain\Error\NotEnoughStock;

/** Records movements while keeping every stock product's available stock at 0 or more (R27). Call it inside a transaction. */
final class StockLedger
{
    public function __construct(private readonly StockMovementRepository $movements) {}

    public function record(StockMovement $movement): void
    {
        $available = $this->movements->availableForUpdate($movement->stockProductId);

        if ($available->plus($movement->quantity)->isNegative()) {
            throw NotEnoughStock::for($movement->stockProductId, $available->toString(), $movement->quantity->toString());
        }

        $this->movements->add($movement);
    }
}
