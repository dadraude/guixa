<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Facades\Gate;

final class SetStockAvailable
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
        private readonly AppendStockAdjustment $append,
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * @param  string  $available  The counted quantity: zero or more, with at most 3 decimals.
     * @param  string  $note  Already translated by the caller: Application stays free of framework i18n (A2).
     */
    public function __invoke(Actor $actor, string $productId, string $available, string $note): void
    {
        Gate::forUser($actor)->authorize('manage');

        $desired = Quantity::fromString($available);

        $this->transactions->run(function () use ($actor, $productId, $desired, $note): void {
            if ($this->products->lock($productId) === null) {
                throw StockProductNotFound::withId($productId);
            }

            // Read under the lock: a sale committed before it must count in the difference.
            $current = $this->movements->available($productId);
            $difference = SignedQuantity::ofThousandths($desired->thousandths - $current->thousandths);
            if ($difference->isZero()) {
                return;
            }

            ($this->append)($actor, $productId, $difference, $note, null);
        });
    }
}
