<?php

namespace Guixa\Sales\Domain;

use DateTimeImmutable;
use DomainException;

final readonly class Basket
{
    /** @param list<BasketLine> $lines */
    public function __construct(
        public string $id,
        public string $householdId,
        public string $householdName,
        public string $state,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public array $lines = [],
        public ?string $validatedBy = null,
        public ?DateTimeImmutable $validatedAt = null,
        public ?int $validatedTotalCents = null,
        public ?string $collectedBy = null,
        public ?DateTimeImmutable $collectedAt = null,
        public ?string $cancelledBy = null,
        public ?DateTimeImmutable $cancelledAt = null,
        /** @var list<BasketAdjustment> */
        public array $adjustments = [],
    ) {}

    /** @return list<BasketLine> */
    public function activeLines(): array
    {
        if ($this->state === 'cancelled') {
            return [];
        }

        return array_values(array_filter($this->lines, fn (BasketLine $line) => $line->removedAt === null));
    }

    public function activeLineFor(string $productId): ?BasketLine
    {
        foreach ($this->activeLines() as $line) {
            if ($line->productId === $productId) {
                return $line;
            }
        }

        return null;
    }

    public function draft(): self
    {
        if ($this->state !== 'draft') {
            throw new DomainException('Only draft baskets can be changed.');
        }

        return $this;
    }

    /** @param list<BasketLine> $lines */
    public function withLines(array $lines): self
    {
        $this->draft();

        return new self($this->id, $this->householdId, $this->householdName, $this->state,
            $this->createdBy, $this->createdAt, $lines);
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (BasketLine $line) => $line->amountCents(), $this->activeLines()));
    }

    public function effectiveTotalCents(): int
    {
        return ($this->validatedTotalCents ?? $this->totalCents())
            - array_sum(array_map(fn (BasketAdjustment $adjustment) => $adjustment->amountCents, $this->adjustments));
    }

    public function withAdjustment(BasketAdjustment $adjustment): self
    {
        if (! in_array($this->state, ['validated', 'collected'], true) || $adjustment->basketId !== $this->id) {
            throw new DomainException('Only a validated basket can be corrected.');
        }

        return new self($this->id, $this->householdId, $this->householdName, $this->state,
            $this->createdBy, $this->createdAt, $this->lines, $this->validatedBy, $this->validatedAt,
            $this->validatedTotalCents, $this->collectedBy, $this->collectedAt, $this->cancelledBy,
            $this->cancelledAt, [...$this->adjustments, $adjustment]);
    }

    public function validated(string $actorId, DateTimeImmutable $when): self
    {
        $this->draft();
        if ($this->activeLines() === []) {
            throw new DomainException('A basket needs contents before validation.');
        }

        return new self($this->id, $this->householdId, $this->householdName, 'validated',
            $this->createdBy, $this->createdAt, $this->lines, $actorId, $when, $this->totalCents());
    }

    public function collected(string $actorId, DateTimeImmutable $when): self
    {
        if ($this->state === 'collected') {
            return $this;
        }
        if ($this->state !== 'validated') {
            throw new DomainException('Only validated baskets can be collected.');
        }

        return new self($this->id, $this->householdId, $this->householdName, 'collected',
            $this->createdBy, $this->createdAt, $this->lines, $this->validatedBy, $this->validatedAt,
            $this->validatedTotalCents, $actorId, $when, adjustments: $this->adjustments);
    }

    public function cancelled(string $actorId, DateTimeImmutable $when): self
    {
        $this->draft();

        return new self($this->id, $this->householdId, $this->householdName, 'cancelled',
            $this->createdBy, $this->createdAt, $this->lines, cancelledBy: $actorId, cancelledAt: $when);
    }
}
