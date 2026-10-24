<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerOrderSent extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The order of producer {$producerId} in cycle {$cycleId} is already sent.");
    }
}
