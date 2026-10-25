<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ReturnItemNotFound extends DomainException
{
    public static function withId(string $returnItemId): self
    {
        return new self("There is no return item {$returnItemId}.");
    }
}
