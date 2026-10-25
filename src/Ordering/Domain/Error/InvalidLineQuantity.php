<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class InvalidLineQuantity extends DomainException
{
    public static function mustBePositive(): self
    {
        return new self('A quantity added to a pickup must be greater than zero.');
    }
}
