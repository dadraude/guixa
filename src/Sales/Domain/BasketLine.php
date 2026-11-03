<?php

namespace Guixa\Sales\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** A retained product reference and price snapshot, including when removed from a draft. */
final readonly class BasketLine
{
    public function __construct(
        public string $id,
        public string $basketId,
        public ?string $productId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public Quantity $quantity,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $removedAt = null,
        public string $kind = 'stock',
        public ?string $returnItemId = null,
        public ?string $sourceOrderLineId = null,
        public ?Quantity $orderedQuantity = null,
        public ?Quantity $allocatedQuantity = null,
    ) {}

    public function amountCents(): int
    {
        $amount = Money::ofCents($this->priceCents)->multiply($this->quantity)->cents;

        return $this->kind === 'return' ? -$amount : $amount;
    }

    public function withQuantity(Quantity $quantity): self
    {
        return new self($this->id, $this->basketId, $this->productId, $this->name, $this->unit,
            $this->priceCents, $this->variable, $this->containersPerUnit, $quantity, $this->createdAt,
            $this->removedAt, $this->kind, $this->returnItemId, $this->sourceOrderLineId, $this->orderedQuantity, $this->allocatedQuantity);
    }

    public function removedAt(DateTimeImmutable $when): self
    {
        return new self($this->id, $this->basketId, $this->productId, $this->name, $this->unit,
            $this->priceCents, $this->variable, $this->containersPerUnit, $this->quantity, $this->createdAt,
            $when, $this->kind, $this->returnItemId, $this->sourceOrderLineId, $this->orderedQuantity, $this->allocatedQuantity);
    }

    /** The allocation changed at the source: the snapshot follows it and a staff override of the actual quantity is reset. */
    public function reallocated(Quantity $allocated): self
    {
        return new self($this->id, $this->basketId, $this->productId, $this->name, $this->unit,
            $this->priceCents, $this->variable, $this->containersPerUnit, $allocated, $this->createdAt,
            $this->removedAt, $this->kind, $this->returnItemId, $this->sourceOrderLineId, $this->orderedQuantity, $allocated);
    }
}
