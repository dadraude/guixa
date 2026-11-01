<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ReturnLineNotInPickup extends DomainException
{
    public static function withId(string $returnItemId): self
    {
        return new self("The pickup has no line of return item {$returnItemId}.");
    }
}
