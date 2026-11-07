<?php

namespace Guixa\Wallet\Application;

use DateTimeImmutable;

final readonly class MovementData
{
    public function __construct(
        public string $id,
        public string $type,
        public int $amountCents,
        public DateTimeImmutable $occurredAt,
        public ?string $note,
        public ?string $cycleId,
        public ?string $basketId = null,
        public ?string $sourceMovementId = null,
        public string $authorName = '',
    ) {}
}
