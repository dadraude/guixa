<?php

namespace Guixa\Ordering\Domain\ValueObject;

use Guixa\Shared\Domain\Money;

/** A return item as Ordering sees it when a manager adds it to a pickup: the fields a line snapshots (R20a). */
final readonly class ReturnItemOffer
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $amount,
    ) {}
}
