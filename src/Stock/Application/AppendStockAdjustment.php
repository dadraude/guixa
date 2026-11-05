<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

/** Internal step for `RecordStockAdjustment` and `SetStockAvailable`, without a lock of its own: the caller must already hold the product lock inside a transaction. */
final class AppendStockAdjustment
{
    public function __construct(
        private readonly StockMovementRepository $movements,
        private readonly StockLedger $ledger,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $productId, SignedQuantity $quantity, string $note, ?string $cycleId): void
    {
        $this->ledger->record(StockMovement::adjustment(
            $this->movements->nextId(),
            $productId,
            $quantity,
            $this->clock->now(),
            $actor->accountId,
            $note,
            $cycleId,
        ));
    }
}
