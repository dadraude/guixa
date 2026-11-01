<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class StockProductInactive extends DomainException
{
    public static function named(string $name): self
    {
        return new self("{$name} is deactivated, so it cannot be added to a pickup.");
    }
}
