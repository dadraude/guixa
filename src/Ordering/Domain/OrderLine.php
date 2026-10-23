<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** One product in a household's order, with the product as it was when the line was last changed (R10). */
final readonly class OrderLine
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $producerId,
        public string $name,
        public string $unit,
        public Money $unitPrice,
        public bool $variable,
        public ?Quantity $containersPerUnit,
        public Quantity $ordered,
        public Quantity $allocated,
        public DateTimeImmutable $createdAt,
        public string $addedBy,
    ) {}
}
