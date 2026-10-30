<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketHasNoContents extends DomainException
{
    public static function create(): self
    {
        return new self('Add contents before validating the basket.');
    }
}
