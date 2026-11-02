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

/** Internal Sales correction: the caller has already authorized the actor. The correction id is unique per product. */
final class CorrectBasketStock
{
    public function __construct(private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements, private readonly StockLedger $ledger,
        private readonly TransactionRunner $transactions, private readonly Clock $clock) {}

    /** @param  string  $quantity  Signed, not zero: positive puts goods back on the shelf. */
    public function __invoke(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void
    {
        $movement = StockMovement::basketCorrection($this->movements->nextId(), $productId,
            SignedQuantity::fromString($quantity), $this->clock->now(), $actor->accountId, $reason,
            $basketId, $correctionId);

        $this->transactions->run(function () use ($productId, $movement): void {
            if ($this->products->lock($productId) === null) {
                throw StockProductNotFound::withId($productId);
            }
            $this->ledger->record($movement);
        });
    }
}
