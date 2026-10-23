<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupDateNotInFuture extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("A pickup date must be after today, and {$date} is not.");
    }
}
