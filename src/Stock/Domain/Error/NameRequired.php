<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class NameRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A name is required.');
    }
}
