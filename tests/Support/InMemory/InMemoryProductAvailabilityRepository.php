<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ProductAvailabilityRepository;

final class InMemoryProductAvailabilityRepository implements ProductAvailabilityRepository
{
    /** @var array<string, array<string, true>> product ids by cycle id */
    private array $unavailable = [];

    public function markUnavailable(string $cycleId, string $productId): void
    {
        $this->unavailable[$cycleId][$productId] = true;
    }

    public function markAvailable(string $cycleId, string $productId): void
    {
        unset($this->unavailable[$cycleId][$productId]);
    }

    public function unavailableIn(string $cycleId): array
    {
        return array_keys($this->unavailable[$cycleId] ?? []);
    }
}
