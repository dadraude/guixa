<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** An append-only correction to an original done pickup line. Positive amounts are additional charges. */
final readonly class PickupAdjustment
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $name,
        public string $unit,
        public Money $unitPrice,
        public int $quantityDeltaThousandths,
        public Money $amount,
        public Quantity $target,
        public DateTimeImmutable $recordedAt,
        public string $recordedBy,
    ) {}
}
