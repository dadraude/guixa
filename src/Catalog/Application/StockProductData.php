<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;

final readonly class StockProductData
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
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
            $product->isActive(),
        );
    }
}
