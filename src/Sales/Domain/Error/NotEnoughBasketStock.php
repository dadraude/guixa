<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class NotEnoughBasketStock extends DomainException
{
    public static function for(string $available): self
    {
        return new self("Only {$available} is available.");
    }
}
