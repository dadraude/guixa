<?php

namespace Guixa\Ordering\Domain\Error;

use DomainException;

/** Way A would replace allocations a manager set by hand; the manager must confirm first (R15, S31). */
final class ManualAllocationsWouldBeLost extends DomainException
{
    /** @param  list<string>  $householdIds */
    private function __construct(string $message, public readonly array $householdIds)
    {
        parent::__construct($message);
    }

    /** @param  list<string>  $householdIds the households whose allocation was set by hand */
    public static function of(string $productId, array $householdIds): self
    {
        return new self(
            "The manual allocations of product {$productId} would be lost: ".implode(', ', $householdIds).'.',
            $householdIds,
        );
    }
}
