<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;

final class UpdateProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(
        Actor $actor,
        string $id,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): void {
        $product = $this->products->get($id) ?? throw ProductNotFound::withId($id);
        $actor->assertProducerOf($product->producerId);

        $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit);
        $this->products->add($product);
    }
}
