<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\HouseholdPickups;
use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;

final class FakeHouseholdPickups implements HouseholdPickups
{
    /** @var array<string, list<PendingHouseholdPickup>> */
    private array $pickups = [];

    /** @param list<PendingHouseholdPickup> $pickups */
    public function set(string $householdId, array $pickups): void
    {
        $this->pickups[$householdId] = $pickups;
    }

    public function pendingFor(string $householdId): array
    {
        return $this->pickups[$householdId] ?? [];
    }
}
