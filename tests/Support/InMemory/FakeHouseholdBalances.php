<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\HouseholdBalances;

final class FakeHouseholdBalances implements HouseholdBalances
{
    /** @var array<string, int> */
    private array $balances = [];

    public function set(string $householdId, int $cents): void
    {
        $this->balances[$householdId] = $cents;
    }

    public function balanceCents(string $householdId): int
    {
        return $this->balances[$householdId] ?? 0;
    }
}
