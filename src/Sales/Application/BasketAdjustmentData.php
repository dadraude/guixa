<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\BasketAdjustment;

final readonly class BasketAdjustmentData
{
    public function __construct(public string $id, public int $amountCents, public string $reason,
        public string $recordedBy, public DateTimeImmutable $occurredAt,
        /** @var list<BasketAdjustmentLineData> */
        public array $lines = []) {}

    public static function from(BasketAdjustment $adjustment): self
    {
        return new self($adjustment->id, $adjustment->amountCents, $adjustment->reason,
            $adjustment->recordedBy, $adjustment->occurredAt,
            array_map(BasketAdjustmentLineData::from(...), $adjustment->lines));
    }
}
