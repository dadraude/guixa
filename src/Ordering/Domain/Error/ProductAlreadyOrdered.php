<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProductAlreadyOrdered extends DomainException
{
    public static function withId(string $productId): self
    {
        return new self("Product {$productId} was ordered, so it is already in the reception and its total follows the received total.");
    }
}
