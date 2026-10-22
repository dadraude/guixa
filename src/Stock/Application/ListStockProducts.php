<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Domain\StockProductRepository;

final class ListStockProducts
{
    public function __construct(private readonly StockProductRepository $products) {}

    /** @return list<StockProductData> */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        return array_map(
            fn ($product) => new StockProductData(
                $product->id,
                $product->name(),
                $product->unit(),
                $product->price()->cents,
                $product->isVariable(),
                $product->containersPerUnit()?->toString(),
                $product->isActive(),
            ),
            $this->products->list(),
        );
    }
}
