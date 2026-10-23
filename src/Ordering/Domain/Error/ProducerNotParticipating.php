<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerNotParticipating extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} does not participate in this cycle.");
    }
}
