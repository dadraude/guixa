<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;

final class DeactivateProduct
{
    public function __construct(private readonly ProductRepository $products) {}

    public function __invoke(Actor $actor, string $id): void
    {
        $product = $this->products->get($id) ?? throw ProductNotFound::withId($id);
        $actor->assertProducerOf($product->producerId);

        if ($product->isStock()) {
            throw NotAllowed::notYours('stock product');
        }

        $product->deactivate();
        $this->products->add($product);
    }
}
