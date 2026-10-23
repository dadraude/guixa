<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class InvalidPickupDate extends DomainException
{
    public static function malformed(string $date): self
    {
        return new self("A pickup date is written YYYY-MM-DD and must exist, not \"{$date}\".");
    }
}
