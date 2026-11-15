<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** A producer was reopened after settlement, so the weekly baskets are stale until its reception is distributed again (R18). */
final class ReceptionReopened extends DomainException
{
    public static function forCycle(string $cycleId): self
    {
        return new self("A reception of cycle {$cycleId} is reopened: distribute it again before validating or editing baskets.");
    }
}
