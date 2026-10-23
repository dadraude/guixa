<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ProductRepository;

/**
 * Internal query: other contexts use it through their own ports, so it takes no actor.
 * Catalog's part of R3: the product is active and so is its producer. Availability in a cycle belongs to Ordering.
 */
final class GetOrderableProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerRepository $producers,
    ) {}

    public function __invoke(string $productId): ?OrderableProductData
    {
        $product = $this->products->get($productId);

        if ($product === null || ! $product->isActive()) {
            return null;
        }

        if (! ($this->producers->get($product->producerId)?->isActive() ?? false)) {
            return null;
        }

        return OrderableProductData::of($product);
    }
}
