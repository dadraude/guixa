<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;

final class RecordStockEntry
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
        private readonly StockLedger $ledger,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param  string  $quantity  A quantity greater than zero, with at most 3 decimals. */
    public function __invoke(Actor $actor, string $stockProductId, string $quantity, ?string $note): void
    {
        $actor->assertManager();

        if ($this->products->get($stockProductId) === null) {
            throw StockProductNotFound::withId($stockProductId);
        }

        $movement = StockMovement::entry(
            $this->movements->nextId(),
            $stockProductId,
            Quantity::fromString($quantity),
            $this->clock->now(),
            $actor->accountId,
            $note,
        );

        $this->transactions->run(fn () => $this->ledger->record($movement));
    }
}
