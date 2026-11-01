<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketNotEditable extends DomainException
{
    public static function create(): self
    {
        return new self('Only a draft basket may be changed.');
    }
}
