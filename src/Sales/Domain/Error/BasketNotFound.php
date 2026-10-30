<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Basket {$id} was not found.");
    }
}
