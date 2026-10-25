<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class StockProductNotFound extends DomainException
{
    public static function withId(string $stockProductId): self
    {
        return new self("There is no stock product {$stockProductId}.");
    }
}
