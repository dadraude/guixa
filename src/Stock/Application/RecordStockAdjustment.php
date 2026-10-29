<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

final class RecordStockAdjustment
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
        private readonly StockLedger $ledger,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param  string  $quantity  Positive or negative, not zero, with at most 3 decimals. */
    public function __invoke(Actor $actor, string $stockProductId, string $quantity, string $note, ?string $cycleId): void
    {
        $actor->assertManager();

        $movement = StockMovement::adjustment(
            $this->movements->nextId(),
            $stockProductId,
            SignedQuantity::fromString($quantity),
            $this->clock->now(),
            $actor->accountId,
            $note,
            $cycleId,
        );

        $this->transactions->run(function () use ($stockProductId, $movement): void {
            if ($this->products->lock($stockProductId) === null) {
                throw StockProductNotFound::withId($stockProductId);
            }

            $this->ledger->record($movement);
        });
    }
}
