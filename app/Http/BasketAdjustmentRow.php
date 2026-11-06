<?php

namespace App\Http;

use DateTimeZone;
use Guixa\Sales\Application\BasketAdjustmentData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketAdjustmentRow
{
    /** @param list<BasketAdjustmentLineRow> $lines */
    public function __construct(public string $id, public int $amountCents, public string $reason,
        public string $recordedBy, public string $occurredAt, public array $lines) {}

    public static function from(BasketAdjustmentData $adjustment): self
    {
        return new self($adjustment->id, $adjustment->amountCents, $adjustment->reason,
            $adjustment->recordedBy, $adjustment->occurredAt->setTimezone(new DateTimeZone(config('guixa.timezone')))->format(DATE_ATOM),
            array_map(BasketAdjustmentLineRow::from(...), $adjustment->lines));
    }
}
