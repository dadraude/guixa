<?php

namespace Guixa\Ordering\Domain\ValueObject;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;

final readonly class ProportionalClaim
{
    public function __construct(
        public string $householdId,
        public Quantity $ordered,
        public DateTimeImmutable $createdAt,
        public string $lineId,
    ) {}
}
