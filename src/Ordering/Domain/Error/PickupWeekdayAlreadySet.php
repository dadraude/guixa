<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class PickupWeekdayAlreadySet extends DomainException
{
    public static function create(): self
    {
        return new self('The cooperative already has a pickup weekday: change it in the settings instead of giving one here.');
    }
}
