<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupWeekdayNotSet extends DomainException
{
    public static function create(): self
    {
        return new self('The cooperative has no pickup weekday yet: give one to open this cycle.');
    }
}
