<?php

namespace Guixa\Ordering\Domain;

use Guixa\Shared\Domain\Money;

/** A return item a manager added to a pickup, with its amount as it was when the line was added (R20a). */
final readonly class ReturnLine
{
    public function __construct(
        public string $id,
        public string $returnItemId,
        public string $name,
        public Money $amount,
        public int $quantity,
    ) {}

    /** The same line, keeping the snapshot, with a new quantity of whole units. */
    public function withQuantity(int $quantity): self
    {
        return new self($this->id, $this->returnItemId, $this->name, $this->amount, $quantity);
    }

    /** What the household is credited for the line (R20a). */
    public function credit(): Money
    {
        return Money::ofCents($this->amount->cents * $this->quantity);
    }
}
