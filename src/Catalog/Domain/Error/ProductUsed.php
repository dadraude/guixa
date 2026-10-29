<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class ProductUsed extends DomainException
{
    public static function create(): self
    {
        return new self('A product that has been used cannot be reclassified or deleted.');
    }
}
