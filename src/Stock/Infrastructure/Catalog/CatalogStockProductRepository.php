<?php

namespace Guixa\Stock\Infrastructure\Catalog;

use Guixa\Catalog\Application\GetStockProduct;
use Guixa\Catalog\Application\ListStockProducts;
use Guixa\Catalog\Application\LockStockProduct;
use Guixa\Catalog\Application\StockProductData;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;

/** Stock's read/lock port; Catalog owns all product metadata writes. */
final class CatalogStockProductRepository implements StockProductRepository
{
    public function __construct(
        private readonly GetStockProduct $get,
        private readonly ListStockProducts $list,
        private readonly LockStockProduct $lockProduct,
    ) {}

    public function get(string $id): ?StockProduct
    {
        $product = ($this->get)($id);

        return $product === null ? null : $this->toStock($product);
    }

    public function lock(string $id): ?StockProduct
    {
        $product = ($this->lockProduct)($id);

        return $product === null ? null : $this->toStock($product);
    }

    public function list(): array
    {
        return array_map($this->toStock(...), ($this->list)());
    }

    private function toStock(StockProductData $product): StockProduct
    {
        return StockProduct::restore(
            $product->id,
            $product->name,
            $product->unit,
            Money::ofCents($product->priceCents),
            $product->variable,
            $product->containersPerUnit === null ? null : Quantity::fromString($product->containersPerUnit),
            $product->active,
            $product->producerId,
        );
    }
}
