<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CutoffOutsideWindow extends DomainException
{
    public static function at(string $at, string $pickupDate): self
    {
        return new self("A cutoff for the pickup on {$pickupDate} falls from 6 days before it at 00:00 until the day before it, and {$at} does not.");
    }
}
