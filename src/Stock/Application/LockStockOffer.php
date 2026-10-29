<?php

namespace Guixa\Stock\Application;

use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProductRepository;

final class LockStockOffer
{
    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
    ) {}

    public function __invoke(string $stockProductId): StockLevelData
    {
        $product = $this->products->lock($stockProductId) ?? throw StockProductNotFound::withId($stockProductId);

        return new StockLevelData(
            $product->id,
            $product->name(),
            $product->unit(),
            $product->price()->cents,
            $product->isVariable(),
            $product->containersPerUnit()?->toString(),
            $product->isActive(),
            $this->movements->available($product->id)->toString(),
            $product->producerId,
        );
    }
}
