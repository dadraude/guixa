<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;

/** What Ordering needs to put a product in a line and snapshot it (R10). */
final readonly class OrderableProductData
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
    ) {}

    public static function of(Product $product): self
    {
        return new self(
            $product->id,
            $product->producerId,
            $product->name(),
            $product->unit(),
            $product->price()->cents,
            $product->isVariable(),
            $product->containersPerUnit()?->toString(),
        );
    }
}
