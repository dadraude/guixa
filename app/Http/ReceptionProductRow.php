<?php

namespace App\Http;

use Guixa\Ordering\Application\ReceptionProductData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A product of a reception: the totals, the difference between received and allocated, and every household's allocation. */
#[TypeScript]
final readonly class ReceptionProductRow
{
    /** @param  list<ReceptionAllocationRow>  $allocations */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
        public bool $nobodyOrdered,
        public string $ordered,
        public string $received,
        public string $allocated,
        public string $difference,
        public bool $balanced,
        public bool $hasManualAllocations,
        public array $allocations,
    ) {}

    public static function from(ReceptionProductData $product): self
    {
        return new self(
            $product->productId,
            $product->name,
            $product->unit,
            $product->variable,
            $product->nobodyOrdered,
            $product->ordered,
            $product->received,
            $product->allocated,
            $product->difference,
            $product->balanced,
            $product->hasManualAllocations,
            array_map(ReceptionAllocationRow::from(...), $product->allocations),
        );
    }
}
