<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerOrderEmpty extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} has no line to send; the scheduler sends and settles an empty order.");
    }
}
