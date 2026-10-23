<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class InvalidPickupWeekday extends DomainException
{
    public static function of(int $isoDay): self
    {
        return new self("A pickup weekday is 1 (Monday) to 7 (Sunday), not {$isoDay}.");
    }
}
