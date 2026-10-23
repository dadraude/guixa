<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class NoHousehold extends DomainException
{
    public static function create(): self
    {
        return new self('Only a member of a household can order.');
    }
}
