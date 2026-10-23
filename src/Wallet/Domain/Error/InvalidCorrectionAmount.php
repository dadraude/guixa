<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class InvalidCorrectionAmount extends DomainException
{
    public static function create(): self
    {
        return new self('A correction cannot be zero.');
    }
}
