<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class PickupAlreadyCharged extends DomainException
{
    public static function create(): self
    {
        return new self('This household was already charged or credited for that cycle.');
    }
}
