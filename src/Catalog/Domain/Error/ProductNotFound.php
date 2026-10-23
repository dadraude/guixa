<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class ProductNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Product {$id} does not exist.");
    }
}
