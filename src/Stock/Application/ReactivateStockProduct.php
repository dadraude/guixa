<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockProductRepository;

final class ReactivateStockProduct
{
    public function __construct(private readonly StockProductRepository $products) {}

    public function __invoke(Actor $actor, string $id): void
    {
        $actor->assertManager();

        $product = $this->products->get($id) ?? throw StockProductNotFound::withId($id);
        $product->reactivate();
        $this->products->add($product);
    }
}
