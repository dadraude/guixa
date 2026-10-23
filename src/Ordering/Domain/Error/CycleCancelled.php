<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CycleCancelled extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Cycle {$id} is cancelled.");
    }
}
