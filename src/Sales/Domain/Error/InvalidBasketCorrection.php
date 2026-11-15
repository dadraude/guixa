<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class InvalidBasketCorrection extends DomainException
{
    public static function create(): self
    {
        return new self('A basket correction needs a validated basket, nonzero amount, reason and identity.');
    }
}
