<?php

namespace App\Http;

use Guixa\Ordering\Application\OfferedProductData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A product a member can add to the order; the price is in euros. */
#[TypeScript]
final readonly class OfferedProductRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public string $price,
        public bool $variable,
    ) {}

    public static function from(OfferedProductData $product): self
    {
        return new self($product->id, $product->name, $product->unit, EuroAmount::format($product->priceCents), $product->variable);
    }
}
