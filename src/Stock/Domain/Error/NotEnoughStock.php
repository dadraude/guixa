<?php

namespace Guixa\Stock\Domain\Error;

use DomainException;

final class NotEnoughStock extends DomainException
{
    public static function for(string $stockProductId, string $available, string $change): self
    {
        return new self("Stock product {$stockProductId} has {$available} available; a change of {$change} would take it below 0.");
    }
}
