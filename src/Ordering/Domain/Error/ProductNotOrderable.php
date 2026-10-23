<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

final class ProductNotOrderable extends DomainException
{
    public static function withId(string $productId): self
    {
        return new self("Product {$productId} is unknown or deactivated, so it cannot be ordered.");
    }

    public static function unavailableInCycle(string $productId, string $cycleId): self
    {
        return new self("Product {$productId} is unavailable in cycle {$cycleId}, so it cannot be ordered.");
    }
}
