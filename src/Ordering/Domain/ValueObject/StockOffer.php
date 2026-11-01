<?php

namespace Guixa\Ordering\Domain\ValueObject;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** A stock product as Ordering sees it when a manager adds it to a pickup: the fields a line snapshots, and what is on hand (R20). */
final readonly class StockOffer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public Money $price,
        public bool $variable,
        public ?Quantity $containersPerUnit,
        public bool $active,
        public Quantity $available,
    ) {}
}
