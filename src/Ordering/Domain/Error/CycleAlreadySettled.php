<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** The cycle is already settled, so a distributed producer order can no longer be reopened (R18; reopening after settlement arrives with T30). */
final class CycleAlreadySettled extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("Cycle {$cycleId} is already settled, so the order of producer {$producerId} cannot be reopened.");
    }
}
