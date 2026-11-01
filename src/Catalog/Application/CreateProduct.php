<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Illuminate\Support\Facades\Gate;

final class CreateProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    /** @return string the new product's id */
    public function __invoke(
        Actor $actor,
        string $producerId,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): string {
        Gate::forUser($actor)->authorize('owns', ['producer', $producerId]);

        $product = Product::create(
            $this->products->nextId(),
            $producerId,
            $name,
            $unit,
            Money::ofCents($priceCents),
            $variable,
            $containersPerUnit,
        );
        $this->products->add($product);

        return $product->id;
    }
}
