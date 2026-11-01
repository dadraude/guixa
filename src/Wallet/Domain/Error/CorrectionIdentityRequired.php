<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class CorrectionIdentityRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A receipt correction needs a unique identity.');
    }
}
