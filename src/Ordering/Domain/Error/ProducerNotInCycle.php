<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerNotInCycle extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("Producer {$producerId} does not take part in cycle {$cycleId}.");
    }
}
