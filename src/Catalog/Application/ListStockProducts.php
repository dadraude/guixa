<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductRepository;

final class ListStockProducts
{
    public function __construct(private readonly ProductRepository $products) {}

    /** @return list<StockProductData> */
    public function __invoke(): array
    {
        return array_map(StockProductData::of(...), $this->products->listStock());
    }
}
