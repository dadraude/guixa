<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class UnknownProducer extends DomainException
{
    public static function withId(string $producerId): self
    {
        return new self("Producer {$producerId} does not exist.");
    }
}
