<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class StockLineNotInPickup extends DomainException
{
    public static function withId(string $stockProductId): self
    {
        return new self("The pickup has no line of stock product {$stockProductId}.");
    }
}
