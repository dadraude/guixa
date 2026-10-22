<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class NobodyOrderedProduct extends DomainException
{
    public static function create(): self
    {
        return new self('A product nobody ordered cannot be allocated proportionally.');
    }
}
