<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CycleNotSettled extends DomainException
{
    public static function withId(string $cycleId): self
    {
        return new self("Cycle {$cycleId} is not settled yet, so it has no pickups.");
    }
}
