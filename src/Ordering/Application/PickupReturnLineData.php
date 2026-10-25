<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\ReturnLine;

/** A return line of a pickup: the item as it was when added, the quantity and what it credits, in cents (R20a). */
final readonly class PickupReturnLineData
{
    public function __construct(
        public string $returnItemId,
        public string $name,
        public int $amountCents,
        public int $quantity,
        public int $creditCents,
    ) {}

    public static function of(ReturnLine $line): self
    {
        return new self($line->returnItemId, $line->name, $line->amount->cents, $line->quantity, $line->credit()->cents);
    }
}
