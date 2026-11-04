<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\HouseholdBaskets;
use Guixa\Membership\Domain\ValueObject\OpenHouseholdBasket;

final class FakeHouseholdBaskets implements HouseholdBaskets
{
    /** @var array<string, list<OpenHouseholdBasket>> */
    private array $baskets = [];

    /** @param list<OpenHouseholdBasket> $baskets */
    public function set(string $householdId, array $baskets): void
    {
        $this->baskets[$householdId] = $baskets;
    }

    public function openFor(string $householdId): array
    {
        return $this->baskets[$householdId] ?? [];
    }
}
