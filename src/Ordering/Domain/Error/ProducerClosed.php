<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerClosed extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} is closed for this cycle.");
    }
}
