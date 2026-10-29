<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;

final class DeactivateStockProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(Actor $actor, string $producerId, string $id): void
    {
        $actor->assertManager();
        $product = $this->products->get($id);

        if ($product === null || ! $product->isStock() || $product->producerId !== $producerId) {
            throw ProductNotFound::withId($id);
        }

        $product->deactivate();
        $this->products->add($product);
    }
}
