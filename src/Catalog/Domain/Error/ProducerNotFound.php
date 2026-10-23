<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class ProducerNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Producer {$id} does not exist.");
    }
}
