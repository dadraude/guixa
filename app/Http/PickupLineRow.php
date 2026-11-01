<?php

namespace App\Http;

use Guixa\Ordering\Application\OrderLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A line of a pickup: what was ordered, allocated and delivered, the unit price and what the delivered quantity costs, in cents. */
#[TypeScript]
final readonly class PickupLineRow
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
        public int $unitPriceCents,
        public string $ordered,
        public string $allocated,
        public string $delivered,
        public bool $adjusted,
        public int $amountCents,
    ) {}

    public static function from(OrderLineData $line): self
    {
        return new self(
            $line->productId,
            $line->name,
            $line->unit,
            $line->variable,
            $line->unitPriceCents,
            $line->quantity,
            $line->allocated ?? $line->quantity,
            $line->delivered ?? $line->quantity,
            $line->adjusted,
            $line->amountCents ?? 0,
        );
    }
}
