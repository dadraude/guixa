<?php

namespace Guixa\Wallet\Domain;

use Guixa\Shared\Domain\Money;

/** Append-only: a movement is never updated or deleted (R32). */
interface MovementRepository
{
    public function nextId(): string;

    /** @throws Error\BasketAlreadyCharged when the basket already has a charge or credit */
    public function add(Movement $movement): void;

    public function findById(string $movementId): ?Movement;

    public function balanceOf(string $householdId): Money;

    /** @return list<Movement> newest first: by `occurredAt`, then id, both descending */
    public function statementOf(string $householdId): array;
}
