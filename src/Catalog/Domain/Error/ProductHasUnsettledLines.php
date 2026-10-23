<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class ProductHasUnsettledLines extends DomainException
{
    public static function create(): self
    {
        return new self('The unit and the variable flag cannot change while the product has lines in a cycle that is not settled.');
    }
}
