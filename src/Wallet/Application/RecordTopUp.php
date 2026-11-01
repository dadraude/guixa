<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Facades\Gate;

final class RecordTopUp
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly Clock $clock,
    ) {}

    /** @return string the new movement's id */
    public function __invoke(Actor $actor, string $householdId, int $amountCents, ?string $note = null): string
    {
        Gate::forUser($actor)->authorize('manage-wallet');

        $movement = Movement::topUp(
            $this->movements->nextId(),
            $householdId,
            Money::ofCents($amountCents),
            $this->clock->now(),
            $actor->accountId,
            $note,
        );
        $this->movements->add($movement);

        return $movement->id;
    }
}
