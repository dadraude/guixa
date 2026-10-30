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
        public string $productId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public Quantity $quantity,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $removedAt = null,
    ) {}

    public function amountCents(): int
    {
        return Money::ofCents($this->priceCents)->multiply($this->quantity)->cents;
    }

    public function withQuantity(Quantity $quantity): self
    {
        return new self($this->id, $this->basketId, $this->productId, $this->name, $this->unit,
            $this->priceCents, $this->variable, $this->containersPerUnit, $quantity, $this->createdAt, $this->removedAt);
    }

    public function removedAt(DateTimeImmutable $when): self
    {
        return new self($this->id, $this->basketId, $this->productId, $this->name, $this->unit,
            $this->priceCents, $this->variable, $this->containersPerUnit, $this->quantity, $this->createdAt, $when);
    }
}
