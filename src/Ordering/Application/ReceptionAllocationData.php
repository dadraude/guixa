<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionAllocationData
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $ordered,
        public string $allocated,
        public bool $byHand,
        public string $delivered,
        public bool $adjusted,
    ) {}
}
