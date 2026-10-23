<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\ValueObject\OrderableProduct;

/** A product a member can add to the order, as it is now. */
final readonly class OfferedProductData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
    ) {}

    public static function of(OrderableProduct $product): self
    {
        return new self($product->id, $product->name, $product->unit, $product->price->cents, $product->variable);
    }
}
