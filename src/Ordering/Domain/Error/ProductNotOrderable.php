<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProductNotOrderable extends DomainException
{
    public static function withId(string $productId): self
    {
        return new self("Product {$productId} is unknown or deactivated, so it cannot be ordered.");
    }
}
