<?php

namespace Guixa\Ordering\Infrastructure\Stock;

use DomainException;
use Guixa\Ordering\Domain\StockCatalog;
use Guixa\Ordering\Domain\ValueObject\StockOffer;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\GetStockOffer;

/** Answers Ordering's questions about stock products through Stock's use cases (A4). */
final class StockModuleStockCatalog implements StockCatalog
{
    public function __construct(private readonly GetStockOffer $getStockOffer) {}

    public function offer(string $stockProductId): ?StockOffer
    {
        // Stock's query reports an unknown id with its not-found domain error, which A4 keeps out of Ordering; it raises no other.
        try {
            $product = ($this->getStockOffer)($stockProductId);
        } catch (DomainException) {
            return null;
        }

        return new StockOffer(
            $product->id,
            $product->name,
            $product->unit,
            Money::ofCents($product->priceCents),
            $product->variable,
            $product->containersPerUnit === null ? null : Quantity::fromString($product->containersPerUnit),
            $product->active,
            Quantity::fromString($product->available),
        );
    }
}
