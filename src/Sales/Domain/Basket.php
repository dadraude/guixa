<?php

namespace Guixa\Sales\Domain;

use DateTimeImmutable;
use DomainException;
use Guixa\Sales\Domain\Error\InvalidBasketCollectionCorrection;
use Guixa\Shared\Domain\Quantity;

final readonly class Basket
{
    /** @param list<BasketLine> $lines */
    public function __construct(
        public string $id,
        public string $householdId,
        public string $householdName,
        public string $state,
        public ?string $createdBy,
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
        /** @var list<BasketCollectionEvent> */
        public array $collectionEvents = [],
        public string $kind = 'stock',
        public ?string $sourceOrderId = null,
        public ?string $referenceCycleId = null,
        /** A weekly basket created after the base was validated, holding only the lines that arrived late (R19). */
        public bool $late = false,
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
            $this->createdBy, $this->createdAt, $lines, kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
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

    /** Original active quantity plus signed corrections, in thousandths. */
    public function effectiveQuantity(string $productId): int
    {
        $original = $this->activeLineFor($productId)?->quantity->thousandths ?? 0;
        foreach ($this->adjustments as $adjustment) {
            foreach ($adjustment->lines as $line) {
                if ($line->productId === $productId) {
                    $original += $line->quantityDeltaThousandths;
                }
            }
        }

        return $original;
    }

    /** A basket that had stock goods and has none left after corrections has nothing to collect. */
    public function goodsFullyReversed(): bool
    {
        $hadGoods = false;
        foreach ($this->activeLines() as $line) {
            if ($line->kind !== 'stock') {
                // Returned empties still have to be collected, so the basket is not fully reversed.
                return false;
            }
            if ($this->effectiveQuantity((string) $line->productId) > 0) {
                return false;
            }
            $hadGoods = true;
        }

        return $hadGoods;
    }

    public function withAdjustment(BasketAdjustment $adjustment): self
    {
        if (! in_array($this->state, ['validated', 'collected'], true) || $adjustment->basketId !== $this->id) {
            throw new DomainException('Only a validated basket can be corrected.');
        }

        return new self($this->id, $this->householdId, $this->householdName, $this->state,
            $this->createdBy, $this->createdAt, $this->lines, $this->validatedBy, $this->validatedAt,
            $this->validatedTotalCents, $this->collectedBy, $this->collectedAt, $this->cancelledBy,
            $this->cancelledAt, [...$this->adjustments, $adjustment], $this->collectionEvents, kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }

    public function validated(string $actorId, DateTimeImmutable $when): self
    {
        $this->draft();
        if ($this->activeLines() === []) {
            throw new DomainException('A basket needs contents before validation.');
        }

        return new self($this->id, $this->householdId, $this->householdName, 'validated',
            $this->createdBy, $this->createdAt, $this->lines, $actorId, $when, $this->totalCents(), kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }

    public function collected(string $actorId, DateTimeImmutable $when, ?string $eventId = null): self
    {
        if ($this->state === 'collected') {
            return $this;
        }
        if ($this->state !== 'validated') {
            throw new DomainException('Only validated baskets can be collected.');
        }

        $events = $this->collectionEvents;
        if ($eventId !== null) {
            $events[] = new BasketCollectionEvent($eventId, $this->id, 'collected', $actorId, $when);
        }

        return new self($this->id, $this->householdId, $this->householdName, 'collected',
            $this->createdBy, $this->createdAt, $this->lines, $this->validatedBy, $this->validatedAt,
            $this->validatedTotalCents, $this->collectedBy ?? $actorId, $this->collectedAt ?? $when,
            adjustments: $this->adjustments, collectionEvents: $events, kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }

    public function collectionCorrected(BasketCollectionEvent $correction): self
    {
        if ($this->state !== 'collected' || $correction->basketId !== $this->id
            || $correction->type !== 'corrected' || trim($correction->reason ?? '') === '') {
            throw InvalidBasketCollectionCorrection::create();
        }

        return new self($this->id, $this->householdId, $this->householdName, 'validated',
            $this->createdBy, $this->createdAt, $this->lines, $this->validatedBy, $this->validatedAt,
            $this->validatedTotalCents, $this->collectedBy, $this->collectedAt, $this->cancelledBy,
            $this->cancelledAt, $this->adjustments, [...$this->collectionEvents, $correction], kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }

    public function cancelled(string $actorId, DateTimeImmutable $when): self
    {
        $this->draft();

        return new self($this->id, $this->householdId, $this->householdName, 'cancelled',
            $this->createdBy, $this->createdAt, $this->lines, cancelledBy: $actorId, cancelledAt: $when, kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }

    public function isWeekly(): bool
    {
        return $this->kind === 'weekly';
    }

    /** Staff sets what the household really takes of a weekly product, zero included, before validation. */
    public function withActualQuantity(string $productId, Quantity $actual): self
    {
        $this->draft();
        $changed = false;
        $lines = array_map(function (BasketLine $line) use ($productId, $actual, &$changed) {
            if ($line->kind !== 'weekly' || $line->removedAt !== null || $line->productId !== $productId) {
                return $line;
            }
            $changed = true;

            return $line->withQuantity($actual);
        }, $this->lines);
        if (! $changed) {
            throw new DomainException('The basket has no weekly line of that product.');
        }

        return $this->rebuilt($this->state, $lines);
    }

    /**
     * Follows the source order while the base is not validated: changed allocations replace the snapshot and reset the actual
     * quantity of those lines, new source lines are added, vanished ones are archived. Stock and return lines stay (R18, S29, S30).
     *
     * @param  list<BasketLine>  $source  weekly lines built from the current source order
     */
    public function synchronizedWith(array $source, DateTimeImmutable $when): self
    {
        if (! $this->isWeekly() || ! in_array($this->state, ['draft', 'nothing_to_collect'], true)) {
            return $this;
        }

        $bySource = [];
        foreach ($source as $line) {
            $bySource[$line->sourceOrderLineId] = $line;
        }
        $lines = [];
        $seen = [];
        foreach ($this->lines as $line) {
            if ($line->kind !== 'weekly' || $line->removedAt !== null) {
                $lines[] = $line;

                continue;
            }
            $current = $bySource[$line->sourceOrderLineId] ?? null;
            $seen[$line->sourceOrderLineId] = true;
            if ($current === null) {
                $lines[] = $line->removedAt($when);
            } elseif ($line->allocatedQuantity === null || ! $line->allocatedQuantity->equals($current->allocatedQuantity)) {
                $lines[] = $line->reallocated($current->allocatedQuantity);
            } else {
                $lines[] = $line;
            }
        }
        foreach ($source as $line) {
            if (! isset($seen[$line->sourceOrderLineId])) {
                $lines[] = $line;
            }
        }

        $candidate = $this->rebuilt($this->state, $lines);

        return $candidate->rebuilt($candidate->hasContent() ? 'draft' : 'nothing_to_collect', $lines);
    }

    /**
     * Adds late-arrival lines to an unvalidated weekly basket. A product already on it takes the new allocation and resets
     * its actual quantity; every other line stays (R18, R19, S91).
     *
     * @param  list<BasketLine>  $arrived  weekly lines built from the source order, for the late products only
     */
    public function withLateLines(array $arrived): self
    {
        if (! $this->isWeekly() || ! in_array($this->state, ['draft', 'nothing_to_collect'], true)) {
            throw new DomainException('Late lines can only join an unvalidated weekly basket.');
        }

        $bySource = [];
        foreach ($arrived as $line) {
            $bySource[$line->sourceOrderLineId] = $line;
        }
        $lines = [];
        foreach ($this->lines as $line) {
            $current = $line->removedAt === null ? ($bySource[$line->sourceOrderLineId] ?? null) : null;
            if ($current === null) {
                $lines[] = $line;

                continue;
            }
            unset($bySource[$line->sourceOrderLineId]);
            $lines[] = $line->allocatedQuantity?->equals($current->allocatedQuantity) ? $line : $line->reallocated($current->allocatedQuantity);
        }

        $merged = [...$lines, ...array_values($bySource)];
        $candidate = $this->rebuilt($this->state, $merged);

        return $candidate->rebuilt($candidate->hasContent() ? 'draft' : 'nothing_to_collect', $merged);
    }

    /** Something to collect: a stock or return line, or a weekly product that was allocated. */
    public function hasContent(): bool
    {
        foreach ($this->activeLines() as $line) {
            if ($line->kind !== 'weekly' || ! ($line->allocatedQuantity?->isZero() ?? true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<BasketLine> $lines */
    private function rebuilt(string $state, array $lines): self
    {
        return new self($this->id, $this->householdId, $this->householdName, $state, $this->createdBy, $this->createdAt,
            $lines, kind: $this->kind, sourceOrderId: $this->sourceOrderId, referenceCycleId: $this->referenceCycleId, late: $this->late);
    }
}
