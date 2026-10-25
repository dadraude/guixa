<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class RedistributionChoiceRequired extends DomainException
{
    public static function create(): self
    {
        return new self('Choose unfinished pickups only or all pickups before redistributing.');
    }
}
