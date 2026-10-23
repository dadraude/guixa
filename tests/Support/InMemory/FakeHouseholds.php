<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

final class FakeHouseholds implements Households
{
    /** @var array<string, HouseholdInfo> */
    private array $known = [];

    public function know(string $householdId, string $name, bool $active = true): void
    {
        $this->known[$householdId] = new HouseholdInfo($householdId, $name, $active);
    }

    public function find(array $householdIds): array
    {
        return array_intersect_key($this->known, array_flip($householdIds));
    }
}
