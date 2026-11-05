<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Facades\Gate;

final class RecordStockAdjustment
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly AppendStockAdjustment $append,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @param  string  $quantity  Positive or negative, not zero, with at most 3 decimals. */
    public function __invoke(Actor $actor, string $productId, string $quantity, string $note, ?string $cycleId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $signed = SignedQuantity::fromString($quantity);

        $this->transactions->run(function () use ($actor, $productId, $signed, $note, $cycleId): void {
            if ($this->products->lock($productId) === null) {
                throw StockProductNotFound::withId($productId);
            }

            ($this->append)($actor, $productId, $signed, $note, $cycleId);
        });
    }
}
