<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class InvalidReturnAmount extends DomainException
{
    public static function create(): self
    {
        return new self('The amount of a return item must be greater than zero.');
    }
}
