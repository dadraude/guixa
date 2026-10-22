<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class StockProductNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Stock product {$id} does not exist.");
    }
}
