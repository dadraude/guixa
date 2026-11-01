<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketAlreadyValidated extends DomainException
{
    public static function create(): self
    {
        return new self('This basket has already been validated.');
    }
}
