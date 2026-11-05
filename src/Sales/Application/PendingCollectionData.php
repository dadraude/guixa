<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;

/** The narrow summary of a validated basket with goods still to hand over; weekly baskets carry their cycle, others none. */
final readonly class PendingCollectionData
{
    public function __construct(
        public string $basketId,
        public string $householdId,
        public string $householdName,
        public bool $weekly,
        public ?string $cycleId,
        public bool $late,
        public DateTimeImmutable $validatedAt,
        public int $totalCents,
    ) {}
}
