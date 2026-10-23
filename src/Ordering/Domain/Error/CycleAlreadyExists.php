<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class CycleAlreadyExists extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("There is already a cycle with pickup on {$date}.");
    }
}
