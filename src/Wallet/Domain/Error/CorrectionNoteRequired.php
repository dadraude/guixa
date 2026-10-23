<?php

namespace Guixa\Wallet\Domain\Error;

use DomainException;

final class CorrectionNoteRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A correction needs a note explaining it.');
    }
}
