<?php

namespace App\Http;

use Guixa\Sales\Application\BasketWeeklyLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A weekly product of a base or late weekly basket: ordered, allocated and actual quantity, with the amounts in cents. */
#[TypeScript]
final readonly class BasketWeeklyLineRow
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public string $ordered,
        public string $allocated,
        public string $quantity,
        public int $unitPriceCents,
        public int $amountCents,
        public string $effectiveQuantity,
        public int $effectiveAmountCents,
        public bool $differsFromAllocation,
    ) {}

    public static function from(BasketWeeklyLineData $line): self
    {
        return new self($line->productId, $line->name, $line->unit, $line->ordered, $line->allocated, $line->quantity,
            $line->priceCents, $line->amountCents, $line->effectiveQuantity, $line->effectiveAmountCents, $line->differsFromAllocation);
    }
}
