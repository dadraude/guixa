<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class DeactivateStockProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $producerId, string $id): void
    {
        $actor->assertManager();
        $this->transactions->run(function () use ($producerId, $id): void {
            $product = $this->products->getForUpdate($id);
            if ($product === null || ! $product->isStock() || $product->producerId !== $producerId) {
                throw ProductNotFound::withId($id);
            }
            $product->deactivate();
            $this->products->add($product);
        });
    }
}
