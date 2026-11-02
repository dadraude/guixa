<?php

namespace Guixa\Sales\Domain;

use DateTimeImmutable;

final readonly class BasketAdjustment
{
    public function __construct(
        public string $id,
        public string $basketId,
        public int $amountCents,
        public string $reason,
        public string $recordedBy,
        public DateTimeImmutable $occurredAt,
        /** @var list<BasketAdjustmentLine> */
        public array $lines = [],
    ) {}

    public function matches(self $other): bool
    {
        return $this->id === $other->id && $this->basketId === $other->basketId
            && $this->amountCents === $other->amountCents && $this->reason === $other->reason
            && $this->recordedBy === $other->recordedBy && $this->lines == $other->lines;
    }
}
