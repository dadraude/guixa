<?php

namespace Guixa\Shared\Domain\Error;

use DomainException;

final class InvalidLocalTime extends DomainException
{
    public static function malformed(string $time): self
    {
        return new self("\"{$time}\" is not a time of day written as HH:MM.");
    }
}
