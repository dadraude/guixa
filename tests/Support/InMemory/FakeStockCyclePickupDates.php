<?php

namespace Tests\Support\InMemory;

use Guixa\Stock\Domain\CyclePickupDates;

final class FakeStockCyclePickupDates implements CyclePickupDates
{
    /** @var array<string, string> */
    private array $known = [];

    public function know(string $cycleId, string $pickupDate): void
    {
        $this->known[$cycleId] = $pickupDate;
    }

    public function forCycles(array $cycleIds): array
    {
        return array_intersect_key($this->known, array_flip($cycleIds));
    }
}
