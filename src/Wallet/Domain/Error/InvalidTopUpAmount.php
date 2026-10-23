<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class InvalidTopUpAmount extends DomainException
{
    public static function create(): self
    {
        return new self('A top-up must be greater than zero.');
    }
}
