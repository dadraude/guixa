<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerHasNoAccount extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} has no account to notify, so its order cannot be sent.");
    }
}
