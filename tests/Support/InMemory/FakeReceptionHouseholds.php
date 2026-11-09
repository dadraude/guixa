<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ReceptionHouseholds;
use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

final class FakeReceptionHouseholds implements ReceptionHouseholds
{
    /** @var list<HouseholdInfo> */
    private array $known = [];

    public function know(string $householdId, string $name, bool $active = true): void
    {
        $this->known[] = new HouseholdInfo($householdId, $name, $active);
    }

    public function active(): array
    {
        $active = array_values(array_filter($this->known, fn (HouseholdInfo $household) => $household->active));
        usort($active, fn (HouseholdInfo $a, HouseholdInfo $b) => [mb_strtolower($a->name), $a->id] <=> [mb_strtolower($b->name), $b->id]);

        return $active;
    }
}
