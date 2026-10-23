<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CycleNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Cycle {$id} does not exist.");
    }
}
