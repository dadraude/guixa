<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class LineNotMarked extends DomainException
{
    public static function forProduct(string $productId): self
    {
        return new self("The line of product {$productId} is not marked as will not be delivered, so it can only change while its producer is open.");
    }
}
