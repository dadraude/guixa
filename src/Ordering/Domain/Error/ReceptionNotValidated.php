<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** A reception is distributed only once it is validated (R17a, R18, S47). */
final class ReceptionNotValidated extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The reception of producer {$producerId} in cycle {$cycleId} is not validated.");
    }
}
