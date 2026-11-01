<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** The producer order is distributed and its reception is read-only until it is reopened (R18). */
final class ProducerDistributed extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The order of producer {$producerId} in cycle {$cycleId} is distributed, so its reception is read-only.");
    }
}
