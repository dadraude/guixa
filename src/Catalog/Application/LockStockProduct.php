<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductRepository;

final class LockStockProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(string $id): ?StockProductData
    {
        $product = $this->products->getForUpdate($id);

        return $product !== null && $product->isStock() ? StockProductData::of($product) : null;
    }
}
