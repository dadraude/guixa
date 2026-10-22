<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class ReturnItemNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Return item {$id} does not exist.");
    }
}
