<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Shared\Application\Actor;

final class ListProductsOfProducer
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
    ) {}

    /** @return list<ProductAdministrationData> */
    public function __invoke(Actor $actor, string $producerId): array
    {
        $actor->assertManager();

        return array_map(
            fn ($product) => ProductAdministrationData::of($product, $this->usage->hasAny($product->id)),
            $this->products->listByProducer($producerId),
        );
    }
}
