<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class InvalidMovementQuantity extends DomainException
{
    public static function entryMustBePositive(): self
    {
        return new self('A stock entry must be greater than zero.');
    }

    public static function adjustmentMustNotBeZero(): self
    {
        return new self('A stock adjustment cannot be zero.');
    }

    public static function saleMustBePositive(): self
    {
        return new self('A quantity sold must be greater than zero.');
    }
}
