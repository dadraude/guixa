<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProductNotFound;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Money;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerCycles $cycles,
    ) {}

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

        if ($product->isStock()) {
            throw NotAllowed::notYours('stock product');
        }

        $product->change($name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit, $this->cycles->hasUnsettledLines($id));
        $this->products->add($product);
    }
}
