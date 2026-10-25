<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class RedistributionCannotExcludeCompleted extends DomainException
{
    public static function create(): self
    {
        return new self('Unfinished pickups cannot receive this total. Include all pickups or cancel.');
    }
}
