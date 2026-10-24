<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProductNotInReception extends DomainException
{
    public static function withId(string $productId): self
    {
        return new self("Product {$productId} is not in this reception: it was not sent and nobody added it.");
    }
}
