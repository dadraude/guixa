<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class FractionalQuantityNotAllowed extends DomainException
{
    public static function forUnit(string $name, string $quantity): self
    {
        return new self("{$name} is ordered in whole units, and {$quantity} is not.");
    }
}
