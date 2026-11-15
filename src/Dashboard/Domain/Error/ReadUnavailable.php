<?php

namespace Guixa\Dashboard\Domain\Error;

use RuntimeException;
use Throwable;

/** A source the dashboard reads could not be read, so its pending work is unknown: never an empty or caught-up result (R15). */
final class ReadUnavailable extends RuntimeException
{
    public static function source(string $source, ?Throwable $previous = null): self
    {
        return new self("The dashboard could not read {$source}.", 0, $previous);
    }
}
