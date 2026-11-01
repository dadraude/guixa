<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerOrderNotSent extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The order of producer {$producerId} in cycle {$cycleId} is not sent yet, so it has no reception.");
    }
}
