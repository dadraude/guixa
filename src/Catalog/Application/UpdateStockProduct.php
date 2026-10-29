<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;

final class UpdateStockProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(Actor $actor, string $producerId, string $id, string $name, string $unit, int $priceCents, bool $variable, ?string $containersPerUnit): void
    {
        $actor->assertManager();
        $product = $this->products->get($id);

        if ($product === null || ! $product->isStock() || $product->producerId !== $producerId) {
            throw ProductNotFound::withId($id);
        }

        $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit);
        $this->products->add($product);
    }
}
