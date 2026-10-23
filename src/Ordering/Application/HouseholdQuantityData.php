<?php

namespace Guixa\Ordering\Application;

final readonly class HouseholdQuantityData
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $quantity,
    ) {}
}
