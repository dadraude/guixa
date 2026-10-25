<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CycleClosed extends DomainException
{
    public static function withId(string $cycleId): self
    {
        return new self("Cycle {$cycleId} is closed and cannot change.");
    }
}
