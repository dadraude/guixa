<?php

namespace Guixa\Sales\Domain;

use DateTimeImmutable;

final readonly class BasketCollectionEvent
{
    public function __construct(
        public string $id,
        public string $basketId,
        public string $type,
        public string $recordedBy,
        public DateTimeImmutable $occurredAt,
        public ?string $reason = null,
    ) {}

    public function matches(self $other): bool
    {
        return $this->id === $other->id && $this->basketId === $other->basketId
            && $this->type === $other->type && $this->recordedBy === $other->recordedBy
            && $this->reason === $other->reason;
    }
}
