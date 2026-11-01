<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class BasketAlreadyCharged extends DomainException
{
    public static function create(): self
    {
        return new self('This basket has already been charged.');
    }
}
