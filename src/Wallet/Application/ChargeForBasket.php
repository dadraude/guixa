<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Facades\Gate;

final class ChargeForBasket
{
    public function __construct(private readonly MovementRepository $movements, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $householdId, string $basketId, int $totalCents): ?string
    {
        Gate::forUser($actor)->authorize('manage-wallet');
        if ($totalCents === 0) {
            return null;
        }

        $id = $this->movements->nextId();
        $total = Money::ofCents(abs($totalCents));
        $movement = $totalCents > 0
            ? Movement::basketCharge($id, $householdId, $total, $basketId, $this->clock->now(), $actor->accountId)
            : Movement::basketCredit($id, $householdId, $total, $basketId, $this->clock->now(), $actor->accountId);
        $this->movements->add($movement);

        return $id;
    }
}
