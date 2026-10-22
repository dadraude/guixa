<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockProductRepository;

final class UpdateStockProduct
{
    public function __construct(private readonly StockProductRepository $products) {}

    public function __invoke(
        Actor $actor,
        string $id,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): void {
        $actor->assertManager();

        $product = $this->products->get($id) ?? throw StockProductNotFound::withId($id);
        $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit);
        $this->products->add($product);
    }
}
