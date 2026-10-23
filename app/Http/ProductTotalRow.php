<?php

namespace App\Http;

use Guixa\Ordering\Application\ProductTotalData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A product's total in a cycle, with the breakdown per household. */
#[TypeScript]
final readonly class ProductTotalRow
{
    /** @param  list<HouseholdQuantityRow>  $households */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public string $quantity,
        public array $households,
    ) {}

    public static function from(ProductTotalData $product): self
    {
        return new self(
            $product->productId,
            $product->name,
            $product->unit,
            $product->quantity,
            array_map(HouseholdQuantityRow::from(...), $product->households),
        );
    }
}
