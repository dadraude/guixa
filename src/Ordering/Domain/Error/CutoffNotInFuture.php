<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CutoffNotInFuture extends DomainException
{
    public static function at(string $at): self
    {
        return new self("A cutoff must be a future moment, and {$at} is not.");
    }
}
