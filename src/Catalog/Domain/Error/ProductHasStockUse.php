<?php

namespace Guixa\Catalog\Domain\Error;

use DomainException;

final class ProductHasStockUse extends DomainException
{
    public static function create(): self
    {
        return new self('The unit and the variable flag cannot change after stock use.');
    }
}
