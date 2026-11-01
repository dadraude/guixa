<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListProducerProducts
{
    public function __construct(private readonly ProductRepository $products) {}

    /** @return list<ProductData> the producer's own products, inactive ones included */
    public function __invoke(Actor $actor, string $producerId): array
    {
        Gate::forUser($actor)->authorize('owns', ['producer', $producerId]);

        return array_values(array_map(
            fn (Product $product) => new ProductData(
                $product->id,
                $product->name(),
                $product->unit(),
                $product->price()->cents,
                $product->isVariable(),
                $product->containersPerUnit()?->toString(),
                $product->isActive(),
            ),
            array_filter($this->products->listByProducer($producerId), fn (Product $product) => ! $product->isStock()),
        ));
    }
}
