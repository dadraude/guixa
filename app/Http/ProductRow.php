<?php

namespace App\Http;

use Guixa\Catalog\Application\ProductData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A product as the producer pages show it; the price is in euros, like the form field. */
#[TypeScript]
final readonly class ProductRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public string $price,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
    ) {}

    public static function from(ProductData $product): self
    {
        return new self(
            $product->id,
            $product->name,
            $product->unit,
            EuroAmount::format($product->priceCents),
            $product->variable,
            $product->containersPerUnit,
            $product->active,
        );
    }
}
