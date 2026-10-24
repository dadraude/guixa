<?php

namespace App\Http;

use Guixa\Ordering\Application\ProducerProductTotalData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One of the producer's products in a cycle: whether it is available and, once the order is sent, the total to deliver. */
#[TypeScript]
final readonly class ProducerProductTotalRow
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $available,
        public ?string $quantity,
    ) {}

    public static function from(ProducerProductTotalData $product): self
    {
        return new self($product->productId, $product->name, $product->unit, $product->available, $product->quantity);
    }
}
