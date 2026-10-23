<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class AdjustmentNoteRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A stock adjustment needs a note saying why.');
    }
}
