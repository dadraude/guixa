<?php

namespace App\Http;

use Guixa\Ordering\Application\PickupAdjustmentData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class PickupAdjustmentRow
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $name,
        public string $unit,
        public string $quantityDelta,
        public int $amountCents,
        public string $recordedAt,
    ) {}

    public static function from(PickupAdjustmentData $line): self
    {
        return new self($line->id, $line->productId, $line->name, $line->unit, $line->quantityDelta, $line->amountCents, $line->recordedAt);
    }
}
