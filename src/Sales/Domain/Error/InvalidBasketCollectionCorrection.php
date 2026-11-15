<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class InvalidBasketCollectionCorrection extends DomainException
{
    public static function create(): self
    {
        return new self('Only a collected basket can have its collection mark corrected with a reason.');
    }
}
