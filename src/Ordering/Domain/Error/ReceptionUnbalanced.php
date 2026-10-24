<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** A reception is distributed only when every product is balanced (R17, R18, S13). */
final class ReceptionUnbalanced extends DomainException
{
    public static function withId(string $cycleId, string $producerId): self
    {
        return new self("The reception of producer {$producerId} in cycle {$cycleId} has unbalanced products.");
    }
}
