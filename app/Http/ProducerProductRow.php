<?php

namespace App\Http;

use Guixa\Catalog\Application\ProductAdministrationData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ProducerProductRow
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
        public bool $stock,
        public bool $used,
    ) {}

    public static function from(ProductAdministrationData $product): self
    {
        return new self(
            $product->id, $product->producerId, $product->name, $product->unit,
            EuroAmount::format($product->priceCents), $product->variable,
            $product->containersPerUnit, $product->active, $product->stock, $product->used,
        );
    }
}
