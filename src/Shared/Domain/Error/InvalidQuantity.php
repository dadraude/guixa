<?php

namespace Guixa\Shared\Domain\Error;

use DomainException;

final class InvalidQuantity extends DomainException
{
    public static function negative(): self
    {
        return new self('A quantity cannot be negative.');
    }

    public static function malformed(string $value): self
    {
        return new self("\"{$value}\" is not a quantity with at most 3 decimal places.");
    }
}
