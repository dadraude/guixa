<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** Only a distributed producer order can be reopened (R18). */
final class ProducerNotDistributed extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The order of producer {$producerId} in cycle {$cycleId} is not distributed.");
    }
}
