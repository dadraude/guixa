<?php

namespace Guixa\Ordering\Domain\ValueObject;

/** What Ordering knows about a household: Membership owns the rest. */
final readonly class HouseholdInfo
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
    ) {}
}
