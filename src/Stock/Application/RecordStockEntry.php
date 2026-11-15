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
use Illuminate\Support\Facades\Gate;

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
    public function __invoke(Actor $actor, string $productId, string $quantity, ?string $note): void
    {
        Gate::forUser($actor)->authorize('manage');

        $movement = StockMovement::entry(
            $this->movements->nextId(),
            $productId,
            Quantity::fromString($quantity),
            $this->clock->now(),
            $actor->accountId,
            $note,
        );

        $this->transactions->run(function () use ($productId, $movement): void {
            if ($this->products->lock($productId) === null) {
                throw StockProductNotFound::withId($productId);
            }

            $this->ledger->record($movement);
        });
    }
}
