<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;

final class GetWalletStatement
{
    public function __construct(private readonly MovementRepository $movements) {}

    public function __invoke(Actor $actor, string $householdId): WalletStatement
    {
        if (! $actor->hasAnyRole(Role::Manager->value, Role::Finance->value)) {
            $actor->assertMemberOf($householdId);
        }

        return new WalletStatement(
            $this->movements->balanceOf($householdId)->cents,
            array_map($this->toData(...), $this->movements->statementOf($householdId)),
        );
    }

    private function toData(Movement $movement): MovementData
    {
        return new MovementData(
            $movement->id,
            $movement->type->value,
            $movement->amount->cents,
            $movement->occurredAt,
            $movement->note,
            $movement->cycleId,
            $movement->basketId,
        );
    }
}
