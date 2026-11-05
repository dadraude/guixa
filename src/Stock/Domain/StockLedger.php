<?php

namespace Guixa\Stock\Domain;

use Guixa\Stock\Domain\Error\NotEnoughStock;

/** Records movements while keeping available stock nonnegative (R27). The caller must hold the Catalog product row lock in a transaction. */
final class StockLedger
{
    public function __construct(private readonly StockMovementRepository $movements) {}

    public function record(StockMovement $movement): void
    {
        $available = $this->movements->available($movement->productId);

        if ($available->plus($movement->quantity)->isNegative()) {
            throw NotEnoughStock::for($movement->productId, $available->toString(), $movement->quantity->toString());
        }

        $this->movements->add($movement);
    }
}
