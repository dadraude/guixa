<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class ProducerAlreadyHasAccount extends DomainException
{
    public static function for(string $producerId): self
    {
        return new self("Producer {$producerId} already has an account.");
    }
}
