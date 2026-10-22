<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class UnitRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A unit is required.');
    }
}
