<?php

namespace Tests\Support\InMemory;

use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\PickupAlreadyCharged;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;

/** @extends InMemoryRepository<Movement> */
final class InMemoryMovementRepository extends InMemoryRepository implements MovementRepository
{
    public function add(Movement $movement): void
    {
        if ($movement->isChargeOrCredit() && $this->hasChargeOrCredit($movement->householdId, $movement->cycleId)) {
            throw PickupAlreadyCharged::create();
        }

        $this->save($movement);
    }

    public function balanceOf(string $householdId): Money
    {
        $cents = array_sum(array_map(
            fn (Movement $movement) => $movement->amount->cents,
            $this->ofHousehold($householdId),
        ));

        return Money::ofCents($cents);
    }

    public function statementOf(string $householdId): array
    {
        $movements = $this->ofHousehold($householdId);
        usort($movements, fn (Movement $a, Movement $b) => [$b->occurredAt, $b->id] <=> [$a->occurredAt, $a->id]);

        return $movements;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }

    /** @return list<Movement> */
    private function ofHousehold(string $householdId): array
    {
        return array_values(array_filter($this->all(), fn (Movement $movement) => $movement->householdId === $householdId));
    }

    private function hasChargeOrCredit(string $householdId, ?string $cycleId): bool
    {
        foreach ($this->ofHousehold($householdId) as $existing) {
            if ($existing->isChargeOrCredit() && $existing->cycleId === $cycleId) {
                return true;
            }
        }

        return false;
    }
}
