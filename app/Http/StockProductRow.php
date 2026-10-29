<?php

namespace App\Http;

use Guixa\Catalog\Application\StockProductData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A stock product as the manage pages show it; the price is in euros, like the form field. */
#[TypeScript]
final readonly class StockProductRow
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $name,
        public string $unit,
        public string $price,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
    ) {}

    public static function from(StockProductData $product): self
    {
        return new self(
            $product->id,
            $product->producerId,
            $product->name,
            $product->unit,
            EuroAmount::format($product->priceCents),
            $product->variable,
            $product->containersPerUnit,
            $product->active,
        );
    }
}
