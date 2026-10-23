<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class NotOnPickupWeekday extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("{$date} is not on the cooperative's pickup weekday.");
    }
}
