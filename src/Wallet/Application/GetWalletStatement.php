<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Domain\AccountNames;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Facades\Gate;

final class GetWalletStatement
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly AccountNames $accountNames,
    ) {}

    public function __invoke(Actor $actor, string $householdId): WalletStatement
    {
        Gate::forUser($actor)->authorize('collectFor', ['household', $householdId]);

        $movements = $this->movements->statementOf($householdId);
        $names = $this->accountNames->forAccounts(array_values(array_unique(
            array_map(fn (Movement $movement) => $movement->recordedBy, $movements),
        )));

        return new WalletStatement(
            $this->movements->balanceOf($householdId)->cents,
            array_map(fn (Movement $movement) => $this->toData($movement, $names), $movements),
        );
    }

    /** @param array<string, string> $names */
    private function toData(Movement $movement, array $names): MovementData
    {
        return new MovementData(
            $movement->id,
            $movement->type->value,
            $movement->amount->cents,
            $movement->occurredAt,
            $movement->note,
            $movement->cycleId,
            $movement->basketId,
            $movement->sourceMovementId,
            $names[$movement->recordedBy] ?? '',
        );
    }
}
