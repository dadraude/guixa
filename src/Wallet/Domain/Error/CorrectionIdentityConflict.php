<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class CorrectionIdentityConflict extends DomainException
{
    public static function create(): self
    {
        return new self('This correction identity was already used for another movement.');
    }
}
