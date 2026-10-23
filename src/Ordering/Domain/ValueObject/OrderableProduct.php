<?php

namespace Guixa\Ordering\Domain\ValueObject;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** Ordering's own copy of what Catalog says about a product it can sell now: the fields a line snapshots (R10). */
final readonly class OrderableProduct
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $name,
        public string $unit,
        public Money $price,
        public bool $variable,
        public ?Quantity $containersPerUnit,
    ) {}
}
