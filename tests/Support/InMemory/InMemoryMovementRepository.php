<?php

namespace Tests\Support\InMemory;

use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\BasketAlreadyCharged;
use Guixa\Wallet\Domain\Error\CorrectionIdentityConflict;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Domain\MovementType;

/** @extends InMemoryRepository<Movement> */
final class InMemoryMovementRepository extends InMemoryRepository implements MovementRepository
{
    public function add(Movement $movement): void
    {
        $existing = $this->findById($movement->id);
        if ($existing !== null && $movement->type === MovementType::Correction) {
            if ($existing->matchesCorrectionReplay($movement)) {
                return;
            }

            throw CorrectionIdentityConflict::create();
        }

        if ($movement->isChargeOrCredit() && $movement->basketId !== null && $this->hasBasketChargeOrCredit($movement->basketId)) {
            throw BasketAlreadyCharged::create();
        }

        $this->save($movement);
    }

    public function findById(string $movementId): ?Movement
    {
        foreach ($this->all() as $movement) {
            if ($movement->id === $movementId) {
                return $movement;
            }
        }

        return null;
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

    private function hasBasketChargeOrCredit(string $basketId): bool
    {
        foreach ($this->all() as $existing) {
            if ($existing->isChargeOrCredit() && $existing->basketId === $basketId) {
                return true;
            }
        }

        return false;
    }
}
