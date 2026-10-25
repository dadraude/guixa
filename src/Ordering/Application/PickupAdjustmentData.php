<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\PickupAdjustment;
use Guixa\Shared\Domain\Quantity;

final readonly class PickupAdjustmentData
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

    public static function of(PickupAdjustment $adjustment): self
    {
        $quantity = Quantity::ofThousandths(abs($adjustment->quantityDeltaThousandths))->toString();

        return new self($adjustment->id, $adjustment->productId, $adjustment->name, $adjustment->unit,
            ($adjustment->quantityDeltaThousandths < 0 ? '-' : '').$quantity,
            $adjustment->amount->cents, $adjustment->recordedAt->format(DATE_ATOM));
    }
}
