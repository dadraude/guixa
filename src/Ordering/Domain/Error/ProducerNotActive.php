<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProducerNotActive extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Producer {$id} is unknown or deactivated, so it cannot participate in a cycle.");
    }
}
