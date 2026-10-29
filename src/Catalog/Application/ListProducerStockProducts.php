<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\StockAvailability;
use Guixa\Shared\Application\Actor;

final class ListProducerStockProducts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly StockAvailability $availability,
    ) {}

    /** @return list<ProducerStockData> */
    public function __invoke(Actor $actor, string $producerId): array
    {
        $actor->assertProducerOf($producerId);
        $products = array_values(array_filter(
            $this->products->listByProducer($producerId),
            fn (Product $product) => $product->isStock(),
        ));
        if ($products === []) {
            return [];
        }

        $quantities = $this->availability->forProducts(array_map(fn (Product $product) => $product->id, $products));

        return array_map(fn (Product $product) => new ProducerStockData(
            $product->id,
            $product->name(),
            $product->unit(),
            $product->price()->cents,
            $product->isActive(),
            $quantities[$product->id] ?? '0.000',
        ), $products);
    }
}
