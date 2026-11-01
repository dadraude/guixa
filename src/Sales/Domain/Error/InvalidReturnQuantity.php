<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class InvalidReturnQuantity extends DomainException
{
    public static function create(): self
    {
        return new self('Enter a positive whole number of returned items.');
    }
}
