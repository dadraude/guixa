<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductRepository;

final class LockWeeklyProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(string $id): ?OrderableProductData
    {
        $product = $this->products->getForUpdate($id);

        return $product !== null && ! $product->isStock() ? OrderableProductData::of($product) : null;
    }
}
