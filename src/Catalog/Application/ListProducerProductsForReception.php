<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductRepository;

/** Internal query for Ordering's reception: every product of the producer, active or not, by name (R14, R16). */
final class ListProducerProductsForReception
{
    public function __construct(private readonly ProductRepository $products) {}

    /** @return list<OrderableProductData> */
    public function __invoke(string $producerId): array
    {
        return array_values(array_map(OrderableProductData::of(...), $this->products->listByProducer($producerId)));
    }
}
