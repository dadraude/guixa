<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class InvalidCutoff extends DomainException
{
    public static function malformed(string $at): self
    {
        return new self("A cutoff is a date and time such as 2026-11-05T12:00, not \"{$at}\".");
    }
}
