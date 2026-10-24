<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/**
 * One product in a household's order, with the product as it was when the line was last changed (R10).
 * `ordered` is 0 for an allocation-only line, which a manager gave to a household that did not order (R16).
 */
final readonly class OrderLine
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $producerId,
        public string $name,
        public string $unit,
        public Money $unitPrice,
        public bool $variable,
        public ?Quantity $containersPerUnit,
        public Quantity $ordered,
        public Quantity $allocated,
        public DateTimeImmutable $createdAt,
        public string $addedBy,
        public bool $allocatedByHand = false,
        public bool $dropped = false,
    ) {}

    public function isOrdered(): bool
    {
        return ! $this->ordered->isZero();
    }

    /** The same line with a new allocation; `byHand` is false when the reception computed it (R15) and true when a manager set it (R16). */
    public function withAllocation(Quantity $allocated, bool $byHand): self
    {
        return new self(
            $this->id,
            $this->productId,
            $this->producerId,
            $this->name,
            $this->unit,
            $this->unitPrice,
            $this->variable,
            $this->containersPerUnit,
            $this->ordered,
            $allocated,
            $this->createdAt,
            $this->addedBy,
            $byHand,
            $this->dropped,
        );
    }

    /** The same line, left out of the pickup for good: never received and never charged (R18). */
    public function asDropped(): self
    {
        return new self(
            $this->id,
            $this->productId,
            $this->producerId,
            $this->name,
            $this->unit,
            $this->unitPrice,
            $this->variable,
            $this->containersPerUnit,
            $this->ordered,
            $this->allocated,
            $this->createdAt,
            $this->addedBy,
            $this->allocatedByHand,
            true,
        );
    }
}
