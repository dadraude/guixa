<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class InvalidBasketQuantity extends DomainException
{
    public static function create(): self
    {
        return new self('Enter a positive quantity in the product step.');
    }
}
