<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class InvalidCorrectionSource extends DomainException
{
    public static function create(): self
    {
        return new self('The original receipt is missing or belongs to another household.');
    }
}
