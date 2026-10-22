<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class InvalidChargeAmount extends DomainException
{
    public static function create(): self
    {
        return new self('A charge or a credit must be greater than zero.');
    }
}
