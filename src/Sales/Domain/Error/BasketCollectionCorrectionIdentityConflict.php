<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class BasketCollectionCorrectionIdentityConflict extends DomainException
{
    public static function create(): self
    {
        return new self('The collection correction identity has different contents.');
    }
}
