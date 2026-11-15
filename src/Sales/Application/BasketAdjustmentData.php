<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\BasketAdjustment;

final readonly class BasketAdjustmentData
{
    public function __construct(public string $id, public int $amountCents, public string $reason,
        public string $authorName, public DateTimeImmutable $occurredAt,
        /** @var list<BasketAdjustmentLineData> */
        public array $lines = []) {}

    /** @param array<string, string> $authorNames name by account id; an author missing from it shows no name */
    public static function from(BasketAdjustment $adjustment, array $authorNames = []): self
    {
        return new self($adjustment->id, $adjustment->amountCents, $adjustment->reason,
            $authorNames[$adjustment->recordedBy] ?? '', $adjustment->occurredAt,
            array_map(BasketAdjustmentLineData::from(...), $adjustment->lines));
    }
}
