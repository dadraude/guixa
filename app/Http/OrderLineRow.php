<?php

namespace App\Http;

use Guixa\Ordering\Application\OrderLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A line, named after its producer even when the producer no longer takes part, with the product as it was when the line was last changed; the price is in euros. */
#[TypeScript]
final readonly class OrderLineRow
{
    public function __construct(
        public string $productId,
        public string $producerId,
        public string $producerName,
        public string $name,
        public string $unit,
        public string $price,
        public bool $variable,
        public string $quantity,
    ) {}

    public static function from(OrderLineData $line, string $producerName): self
    {
        return new self(
            $line->productId,
            $line->producerId,
            $producerName,
            $line->name,
            $line->unit,
            EuroAmount::format($line->unitPriceCents),
            $line->variable,
            $line->quantity,
        );
    }
}
