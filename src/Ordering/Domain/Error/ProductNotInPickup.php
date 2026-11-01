<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProductNotInPickup extends DomainException
{
    public static function withId(string $productId): self
    {
        return new self("The pickup has no line of product {$productId}.");
    }
}
