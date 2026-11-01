<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Facades\Gate;

final class ChargeForPickup
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  int  $totalCents  what the pickup costs: a negative total is a credit, zero records nothing (R22)
     * @return string|null the new movement's id, or null when nothing was recorded
     */
    public function __invoke(Actor $actor, string $householdId, string $cycleId, int $totalCents): ?string
    {
        Gate::forUser($actor)->authorize('manage');

        if ($totalCents === 0) {
            return null;
        }

        $id = $this->movements->nextId();
        $total = Money::ofCents(abs($totalCents));
        $movement = $totalCents > 0
            ? Movement::charge($id, $householdId, $total, $cycleId, $this->clock->now(), $actor->accountId)
            : Movement::credit($id, $householdId, $total, $cycleId, $this->clock->now(), $actor->accountId);
        $this->movements->add($movement);

        return $id;
    }
}
