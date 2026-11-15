<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** The cycle is settled, so no producer can be added to it any more (R4a). */
final class CycleSettled extends DomainException
{
    public static function withId(string $cycleId): self
    {
        return new self("Cycle {$cycleId} is settled and no producer can be added.");
    }
}
