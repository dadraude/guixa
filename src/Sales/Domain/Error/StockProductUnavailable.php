<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class StockProductUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('This stock product is unavailable.');
    }
}
