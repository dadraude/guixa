<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class ReturnItemUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('The return item is not available.');
    }
}
