<?php

namespace App\Http;

use Guixa\Ordering\Application\PickupReturnLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A return line of a pickup: the item as it was when added, the quantity and what it credits, in cents. */
#[TypeScript]
final readonly class PickupReturnLineRow
{
    public function __construct(
        public string $returnItemId,
        public string $name,
        public int $amountCents,
        public int $quantity,
        public int $creditCents,
    ) {}

    public static function from(PickupReturnLineData $line): self
    {
        return new self($line->returnItemId, $line->name, $line->amountCents, $line->quantity, $line->creditCents);
    }
}
