<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class NotEnoughStock extends DomainException
{
    public static function forProduct(string $name, string $available, string $requested): self
    {
        return new self("Only {$available} of {$name} are available, and the pickup would have {$requested}.");
    }
}
