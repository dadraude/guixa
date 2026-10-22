<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;

final class CreateStockProduct
{
    public function __construct(private readonly StockProductRepository $products) {}

    /** @return string the new stock product's id */
    public function __invoke(
        Actor $actor,
        string $name,
        string $unit,
        int $priceCents,
        bool $variable,
        ?string $containersPerUnit,
    ): string {
        $actor->assertManager();

        $product = StockProduct::create(
            $this->products->nextId(),
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
