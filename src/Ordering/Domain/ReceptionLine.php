<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;

/** One household's share of a product in a reception: what it ordered and what it is allocated (R14, R16). */
final readonly class ReceptionLine
{
    public function __construct(
        public string $householdId,
        public string $lineId,
        public Quantity $ordered,
        public Quantity $allocated,
        public bool $allocatedByHand,
        public DateTimeImmutable $createdAt,
    ) {}
}
