<?php

namespace Guixa\Ordering\Domain;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** A stock product a manager added to a pickup, with the product as it was when the line was added (R20). */
final readonly class StockLine
{
    public function __construct(
        public string $id,
        public string $stockProductId,
        public string $name,
        public string $unit,
        public Money $unitPrice,
        public bool $variable,
        public ?Quantity $containersPerUnit,
        public Quantity $quantity,
    ) {}

    /** The same line, keeping the snapshot, with a new quantity. */
    public function withQuantity(Quantity $quantity): self
    {
        return new self($this->id, $this->stockProductId, $this->name, $this->unit, $this->unitPrice, $this->variable, $this->containersPerUnit, $quantity);
    }

    /** What the household pays for the line, rounded half up to the cent (R22). */
    public function amount(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
