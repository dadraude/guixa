<?php

namespace Guixa\Sales\Domain\Error;

use DomainException;

final class HouseholdUnavailable extends DomainException
{
    public static function create(): self
    {
        return new self('Choose an active household.');
    }
}
