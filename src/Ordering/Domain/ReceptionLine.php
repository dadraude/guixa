<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;

/** One household's share of a product in a reception: what it ordered and what it is allocated; `delivered` differs from the allocation after a pickup adjustment (R14, R16, R19). */
final readonly class ReceptionLine
{
    public function __construct(
        public string $householdId,
        public string $lineId,
        public Quantity $ordered,
        public Quantity $allocated,
        public Quantity $delivered,
        public bool $allocatedByHand,
        public DateTimeImmutable $createdAt,
        public bool $done = false,
    ) {}

    public function isAdjusted(): bool
    {
        return ! $this->delivered->equals($this->allocated);
    }
}
