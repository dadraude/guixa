<?php

namespace App\Http;

use Guixa\Sales\Application\BasketAdjustmentData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketAdjustmentRow
{
    public function __construct(public string $id, public int $amountCents, public string $reason,
        public string $recordedBy, public string $occurredAt) {}

    public static function from(BasketAdjustmentData $adjustment): self
    {
        return new self($adjustment->id, $adjustment->amountCents, $adjustment->reason,
            $adjustment->recordedBy, $adjustment->occurredAt->format(DATE_ATOM));
    }
}
