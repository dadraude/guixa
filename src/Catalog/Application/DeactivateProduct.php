<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Error\NotAllowed;

final class DeactivateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $id): void
    {
        $this->transactions->run(function () use ($actor, $id): void {
            $product = $this->products->getForUpdate($id) ?? throw ProductNotFound::withId($id);
            $actor->assertProducerOf($product->producerId);
            if ($product->isStock()) {
                throw NotAllowed::notYours('stock product');
            }
            $product->deactivate();
            $this->products->add($product);
        });
    }
}
