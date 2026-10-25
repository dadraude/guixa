<?php

namespace Guixa\Ordering\Application;

final readonly class HouseholdContainerCountData
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $count,
    ) {}
}
