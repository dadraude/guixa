<?php

namespace App\Http;

use Guixa\Ordering\Application\PickupStockLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A stock line of a pickup: the product as it was when added, the quantity and what it costs, in cents. */
#[TypeScript]
final readonly class PickupStockLineRow
{
    public function __construct(
        public string $stockProductId,
        public string $name,
        public string $unit,
        public bool $variable,
        public int $unitPriceCents,
        public string $quantity,
        public int $amountCents,
    ) {}

    public static function from(PickupStockLineData $line): self
    {
        return new self($line->stockProductId, $line->name, $line->unit, $line->variable, $line->unitPriceCents, $line->quantity, $line->amountCents);
    }
}
