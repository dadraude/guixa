<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class LineMarkedNotDelivered extends DomainException
{
    public static function forProduct(string $productId): self
    {
        return new self("The household's line of product {$productId} is marked as not delivered, so it cannot be allocated.");
    }
}
