<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketCorrectionIdentityConflict extends DomainException
{
    public static function create(): self
    {
        return new self('The basket correction identity has different contents.');
    }
}
