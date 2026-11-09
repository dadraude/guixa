<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Illuminate\Support\Facades\Gate;

final class CreateStockProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerRepository $producers,
    ) {}

    public function __invoke(
        Actor $actor,
        string $producerId,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): string {
        Gate::forUser($actor)->authorize('administer-catalogue');

        if ($this->producers->get($producerId) === null) {
            throw ProducerNotFound::withId($producerId);
        }

        $product = Product::create(
            $this->products->nextId(),
            $producerId,
            $name,
            $unit,
            Money::ofCents($priceCents),
            $variable,
            $containersPerUnit,
            stock: true,
        );
        $this->products->add($product);

        return $product->id;
    }
}
