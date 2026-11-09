<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListProductsOfProducer
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductUsage $usage,
    ) {}

    /** @return list<ProductAdministrationData> */
    public function __invoke(Actor $actor, string $producerId): array
    {
        Gate::forUser($actor)->authorize('administer-catalogue');

        return array_map(
            fn (Product $product) => $this->row($actor, $product),
            $this->products->listByProducer($producerId),
        );
    }

    /** Stock products and their classification stay with the Manager until Catalogue administers stock. */
    private function row(Actor $actor, Product $product): ProductAdministrationData
    {
        $gate = Gate::forUser($actor);

        return ProductAdministrationData::of(
            $product,
            $this->usage->hasAny($product->id),
            canEdit: $product->isStock() ? $gate->allows('manage') : $gate->allows('update', $product),
            canChangeClassification: $gate->allows('manage'),
            canDelete: $gate->allows('delete', $product),
        );
    }
}
