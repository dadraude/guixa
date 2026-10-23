<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class InvalidPrice extends DomainException
{
    public static function create(): self
    {
        return new self('The price cannot be negative.');
    }
}
