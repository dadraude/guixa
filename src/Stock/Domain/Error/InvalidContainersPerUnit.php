<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class InvalidContainersPerUnit extends DomainException
{
    public static function create(): self
    {
        return new self('Containers per unit must be a quantity greater than zero.');
    }
}
