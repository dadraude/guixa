<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class InvalidCutoffRule extends DomainException
{
    public static function daysOutOfRange(int $days): self
    {
        return new self("A cutoff is 1 to 6 days before the pickup date, not {$days}.");
    }

    public static function malformedTime(string $time): self
    {
        return new self("A cutoff time is written HH:MM, not \"{$time}\".");
    }
}
