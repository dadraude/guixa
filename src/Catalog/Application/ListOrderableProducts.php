<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;

/** Internal query for Ordering: a producer's active products, empty when the producer is unknown or inactive (R3). */
final class ListOrderableProducts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerRepository $producers,
    ) {}

    /** @return list<OrderableProductData> by name */
    public function __invoke(string $producerId): array
    {
        if (! ($this->producers->get($producerId)?->isActive() ?? false)) {
            return [];
        }

        $active = array_filter($this->products->listByProducer($producerId), fn (Product $product) => $product->isActive());

        return array_values(array_map(OrderableProductData::of(...), $active));
    }
}
