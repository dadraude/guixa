<?php

namespace Guixa\Sales\Infrastructure\Catalog;

use Guixa\Catalog\Application\LockStockProduct;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;

final class CatalogStockProducts implements StockProducts
{
    public function __construct(private readonly LockStockProduct $products) {}

    public function lock(string $productId): ?StockProductSnapshot
    {
        $product = ($this->products)($productId);
        if ($product === null) {
            return null;
        }

        return new StockProductSnapshot($product->id, $product->name, $product->unit,
            $product->priceCents, $product->variable, $product->containersPerUnit, $product->active);
    }
}
