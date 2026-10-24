<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerStillOpen extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} is still open for this cycle, so its order cannot be sent yet.");
    }
}
